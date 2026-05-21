<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingSkillRequirement;
use Illuminate\Support\Collection;
use RuntimeException;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Sde\InvType;

class FleetSkillReviewService
{
    public function __construct(
        private FleetEsiService $fleetEsi,
        private SkillRequirementCalculator $calculator,
        private CharacterSkillSnapshotService $characters,
        private PersonalSkillCheckService $personalSkillCheck,
    ) {}

    public function run(int $doctrineId, ?int $fittingId = null): array
    {
        $doctrine = Doctrine::with(['fittings.ship'])->findOrFail($doctrineId);
        $fittings = $this->selectedFittings($doctrine, $fittingId);
        $fleet = $this->fleetEsi->currentFleetMembers();
        $members = collect($fleet['members']);

        if ($members->isEmpty()) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_empty'));
        }

        $fittingChecks = $this->fittingChecks($fittings, $doctrine->id);
        $allRequirements = $this->allRequirements($fittingChecks);
        $characterIds = $members->pluck('character_id')->unique()->values()->all();
        $characters = CharacterInfo::whereIn('character_id', $characterIds)
            ->select('character_id', 'name', 'title')
            ->get();
        $snapshots = $this->characters->forCharactersWithRequiredSkills($characters, $allRequirements);
        $charactersById = $characters->keyBy('character_id');
        $shipsById = InvType::whereIn('typeID', $members->pluck('ship_type_id')->unique()->values()->all())
            ->select('typeID', 'typeName')
            ->get()
            ->keyBy('typeID');
        $fittingsByShip = $fittings->groupBy(fn (Fitting $fitting) => (int) $fitting->ship_type_id);
        $rows = [];

        foreach ($members as $member) {
            $shipTypeId = (int) $member['ship_type_id'];
            $matchingFittings = $fittingsByShip->get($shipTypeId, collect());

            if ($matchingFittings->isEmpty()) {
                $rows[] = $this->unmatchedRow($member, $charactersById, $shipsById);
                continue;
            }

            foreach ($matchingFittings as $fitting) {
                $rows[] = $this->reviewRow($member, $charactersById, $shipsById, $snapshots, $fitting, $fittingChecks[$fitting->fitting_id]);
            }
        }

        $rowCollection = collect($rows);
        $matchedRows = $rowCollection->where('matched', true);
        $shipTotals = $this->shipTotals($fittings, $members, $rowCollection, $shipsById);

        return [
            'fleetId' => $fleet['fleet_id'],
            'doctrine' => [
                'id' => $doctrine->id,
                'name' => $doctrine->name,
            ],
            'rows' => $rows,
            'shipTotals' => $shipTotals,
            'totals' => [
                'members' => $members->count(),
                'rows' => count($rows),
                'failed' => $matchedRows->where('status', 'failed')->count(),
                'entry' => $matchedRows->where('status', 'entry')->count(),
                'advanced' => $matchedRows->where('status', 'advanced')->count(),
                'unreviewed' => $rowCollection->where('matched', false)->count(),
                'fleetDps' => collect($shipTotals['reviewed'])->sum('fleet_dps'),
                'fleetDph' => collect($shipTotals['reviewed'])->sum('fleet_dph'),
                'theoretical_dps' => collect($shipTotals['reviewed'])->sum(fn ($r) => $r['members'] * (float) ($r['minimum_dps'] ?? 0)),
                'theoretical_dph' => collect($shipTotals['reviewed'])->sum(fn ($r) => $r['members'] * (float) ($r['minimum_dph'] ?? 0)),
            ],
        ];
    }

    private function selectedFittings(Doctrine $doctrine, ?int $fittingId): Collection
    {
        if ($fittingId === null) {
            return $doctrine->fittings->values();
        }

        $fittings = $doctrine->fittings
            ->where('fitting_id', $fittingId)
            ->values();

        if ($fittings->isEmpty()) {
            throw new RuntimeException(trans('fitting::doctrine.fleet_error_fitting_not_in_doctrine'));
        }

        return $fittings;
    }

    private function fittingChecks(Collection $fittings, int $doctrineId): array
    {
        $checks = [];

        foreach ($fittings as $fitting) {
            $shipSkills = $this->skillMap($this->calculator->calculateForTypeId($fitting->ship_type_id));
            $minimumList = $this->personalSkillCheck->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM, $doctrineId);
            $advancedList = $this->personalSkillCheck->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED, $doctrineId);
            $advancedList = $this->personalSkillCheck->normalizeAdvancedAgainstMinimum($minimumList, $advancedList);

            $checks[$fitting->fitting_id] = [
                'ship' => $shipSkills,
                'minimum' => $this->skillMap($minimumList),
                'advanced' => $this->skillMap($advancedList),
            ];
        }

        return $checks;
    }

    private function allRequirements(array $fittingChecks): array
    {
        $requirements = [];

        foreach ($fittingChecks as $checks) {
            $requirements = collect($requirements)
                ->merge($this->requirementsFromSkillMap($checks['ship']))
                ->merge($this->requirementsFromSkillMap($checks['minimum']))
                ->merge($this->requirementsFromSkillMap($checks['advanced']))
                ->unique('typeId')
                ->values()
                ->all();
        }

        return $requirements;
    }

    private function reviewRow(array $member, Collection $charactersById, Collection $shipsById, Collection $snapshots, Fitting $fitting, array $checks): array
    {
        $characterId = (int) $member['character_id'];
        $character = $charactersById->get($characterId);
        $snapshot = $snapshots->get($characterId);
        $ship = $shipsById->get((int) $member['ship_type_id']);

        if (! $snapshot) {
            return $this->baseRow($member, $character, $ship, [
                'fitting_id' => $fitting->fitting_id,
                'fitting_name' => $fitting->name,
                'check_type' => trans('fitting::doctrine.fleet_check_type_no_skill_data'),
                'matched' => true,
                'status' => 'failed',
                'ship_passed' => false,
                'minimum' => false,
                'advanced' => null,
            ]);
        }

        $shipPassed = $this->meetsRequirements($snapshot['skill'], $checks['ship']);
        $minimum = $shipPassed && $this->meetsRequirements($snapshot['skill'], $checks['minimum']);
        $advanced = count($checks['advanced']) > 0 ? ($shipPassed && $this->meetsRequirements($snapshot['skill'], $checks['advanced'])) : null;
        $status = ! $minimum ? 'failed' : ($advanced === true ? 'advanced' : 'entry');

        return $this->baseRow($member, $character, $ship, [
            'fitting_id' => $fitting->fitting_id,
            'fitting_name' => $fitting->name,
            'check_type' => trans('fitting::doctrine.fleet_check_type_fitting'),
            'matched' => true,
            'status' => $status,
            'ship_passed' => $shipPassed,
            'minimum' => $minimum,
            'advanced' => $advanced,
        ]);
    }

    private function unmatchedRow(array $member, Collection $charactersById, Collection $shipsById): array
    {
        $character = $charactersById->get((int) $member['character_id']);
        $ship = $shipsById->get((int) $member['ship_type_id']);

        return $this->baseRow($member, $character, $ship, [
            'fitting_id' => null,
            'fitting_name' => '',
            'check_type' => trans('fitting::doctrine.fleet_check_type_no_match'),
            'matched' => false,
            'status' => 'failed',
            'ship_passed' => false,
            'minimum' => false,
            'advanced' => null,
        ]);
    }

    private function baseRow(array $member, ?CharacterInfo $character, ?InvType $ship, array $check): array
    {
        return array_merge([
            'character_id' => (int) $member['character_id'],
            'character_name' => $character?->name ?? '#'.$member['character_id'],
            'nickname' => $character?->title ?? '',
            'role' => $member['role'],
            'role_name' => $member['role_name'] ?: $member['role'],
            'ship_type_id' => (int) $member['ship_type_id'],
            'ship_type' => $ship?->typeName ?? '#'.$member['ship_type_id'],
        ], $check);
    }

    private function shipTotals(Collection $fittings, Collection $members, Collection $rows, Collection $shipsById): array
    {
        $rowsByFitting = $rows->where('matched', true)->groupBy('fitting_id');
        $reviewed = [];

        foreach ($fittings as $fitting) {
            $fitRows = $rowsByFitting->get($fitting->fitting_id, collect());
            $failed = $fitRows->where('status', 'failed')->count();
            $entry = $fitRows->where('status', 'entry')->count();
            $advanced = $fitRows->where('status', 'advanced')->count();
            $minimumDps = (float) ($fitting->minimum_dps ?? 0);
            $minimumDph = (float) ($fitting->minimum_dph ?? 0);
            $advancedDps = (float) ($fitting->advanced_dps ?? 0);
            $advancedDph = (float) ($fitting->advanced_dph ?? 0);

            $reviewed[] = [
                'ship_type_id' => (int) $fitting->ship_type_id,
                'ship_type' => $fitting->ship?->typeName ?? '#'.$fitting->ship_type_id,
                'fitting_id' => (int) $fitting->fitting_id,
                'fitting_name' => $fitting->name,
                'failed' => $failed,
                'entry' => $entry,
                'advanced' => $advanced,
                'members' => $fitRows->count(),
                'minimum_dps' => $fitting->minimum_dps,
                'minimum_dph' => $fitting->minimum_dph,
                'advanced_dps' => $fitting->advanced_dps,
                'advanced_dph' => $fitting->advanced_dph,
                'fleet_dps' => ($entry * $minimumDps) + ($advanced * $advancedDps),
                'fleet_dph' => ($entry * $minimumDph) + ($advanced * $advancedDph),
            ];
        }

        $reviewedShipTypeIds = $fittings->pluck('ship_type_id')->map(fn ($shipTypeId) => (int) $shipTypeId)->unique()->all();
        $unreviewed = $members
            ->filter(fn ($member) => ! in_array((int) $member['ship_type_id'], $reviewedShipTypeIds, true))
            ->groupBy(fn ($member) => (int) $member['ship_type_id'])
            ->map(function (Collection $shipMembers, $shipTypeId) use ($shipsById) {
                $shipTypeId = (int) $shipTypeId;
                $ship = $shipsById->get($shipTypeId);

                return [
                    'ship_type_id' => $shipTypeId,
                    'ship_type' => $ship?->typeName ?? '#'.$shipTypeId,
                    'members' => $shipMembers->count(),
                ];
            })
            ->sortBy('ship_type')
            ->values()
            ->all();

        return [
            'reviewed' => $reviewed,
            'unreviewed' => $unreviewed,
        ];
    }

    private function skillMap(array $skills): array
    {
        return collect($skills)
            ->mapWithKeys(fn ($skill) => [$skill['typeId'] => $skill['level']])
            ->all();
    }

    private function requirementsFromSkillMap(array $skills): array
    {
        return collect($skills)
            ->map(fn ($level, $typeId) => [
                'typeId' => $typeId,
                'level' => $level,
            ])
            ->values()
            ->all();
    }

    private function meetsRequirements(array $characterSkills, array $requirements): bool
    {
        foreach ($requirements as $skillId => $level) {
            if (! isset($characterSkills[$skillId]) || $characterSkills[$skillId]['level'] < $level) {
                return false;
            }
        }

        return true;
    }
}
