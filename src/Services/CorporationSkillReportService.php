<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use Illuminate\Support\Collection;
use Seat\Eveapi\Models\Character\CharacterInfo;

class CorporationSkillReportService
{
    public function __construct(
        private SkillRequirementCalculator $calculator,
        private CharacterSkillSnapshotService $characters,
        private PersonalSkillCheckService $personalSkillCheck,
    ) {}

    public function run(array $allianceIds, array $corporationIds, int $doctrineId): array
    {
        $doctrine = Doctrine::with('fittings.ship')->where('id', $doctrineId)->firstOrFail();

        return $this->runForFittings($allianceIds, $corporationIds, $doctrine->fittings);
    }

    public function runForFitting(array $allianceIds, array $corporationIds, int $fittingId): array
    {
        $fitting = Fitting::with('ship')->where('fitting_id', $fittingId)->firstOrFail();

        return $this->runForFittings($allianceIds, $corporationIds, collect([$fitting]));
    }

    public function runForFittings(array $allianceIds, array $corporationIds, Collection $fittings): array
    {
        $fittingChecks = [];
        $allRequirements = [];

        foreach ($fittings as $fitting) {
            $shipSkills = $this->skillMap($this->calculator->calculateForTypeId($fitting->ship_type_id));
            $minimumSkills = $this->skillMap($this->personalSkillCheck->requirementsForTier($fitting, 'minimum'));
            $advancedSkills = $this->skillMap($this->personalSkillCheck->requirementsForTier($fitting, 'advanced'));

            $fittingChecks[$fitting->fitting_id] = [
                'ship' => $shipSkills,
                'minimum' => $minimumSkills,
                'advanced' => $advancedSkills,
            ];

            $allRequirements = collect($allRequirements)
                ->merge($this->requirementsFromSkillMap($shipSkills))
                ->merge($this->requirementsFromSkillMap($minimumSkills))
                ->merge($this->requirementsFromSkillMap($advancedSkills))
                ->unique('typeId')
                ->values()
                ->all();
        }

        $characters = CharacterInfo::whereHas('affiliation', function ($affiliation) use ($corporationIds, $allianceIds) {
            if (count($allianceIds) > 0) {
                $affiliation->whereIn('alliance_id', $allianceIds);
            }

            if (count($corporationIds) > 0) {
                $affiliation->whereIn('corporation_id', $corporationIds);
            }
        })
            ->select('character_id', 'name', 'title')
            ->get();

        $characterSnapshots = $this->characters->forCharactersWithRequiredSkills($characters, $allRequirements);
        $data = [
            'fittings' => [],
            'fittingDetails' => [],
            'totals' => [],
            'totalsByFittingId' => [],
            'chars' => [],
            'charsById' => [],
        ];

        foreach ($fittings as $fitting) {
            $fittingId = $fitting->fitting_id;
            $shipSkills = $fittingChecks[$fittingId]['ship'];
            $minimumSkills = $fittingChecks[$fittingId]['minimum'];
            $advancedSkills = $fittingChecks[$fittingId]['advanced'];

            $data['fittings'][] = $fitting->name;
            $data['fittingDetails'][] = [
                'id' => $fittingId,
                'name' => $fitting->name,
                'shipType' => $fitting->ship->typeName,
            ];
            $data['totals'][$fitting->name] = [
                'ship' => 0,
                'fit' => 0,
                'minimum' => 0,
                'advanced' => 0,
            ];
            $data['totalsByFittingId'][$fittingId] = [
                'ship' => 0,
                'fit' => 0,
                'minimum' => 0,
                'advanced' => 0,
            ];

            foreach ($characterSnapshots as $characterId => $character) {
                $ship = $this->meetsRequirements($character['skill'], $shipSkills);
                $minimum = $this->meetsRequirements($character['skill'], $minimumSkills);
                $advanced = count($advancedSkills) > 0 ? $this->meetsRequirements($character['skill'], $advancedSkills) : null;
                $legacyFit = $minimum;

                $data['chars'][$character['name']][$fitting->name] = [
                    'ship' => $ship,
                    'fit' => $legacyFit,
                    'minimum' => $minimum,
                    'advanced' => $advanced,
                ];
                $data['charsById'][$characterId]['name'] = $character['name'];
                $data['charsById'][$characterId]['title'] = $character['title'];
                $data['charsById'][$characterId]['fittings'][$fittingId] = [
                    'ship' => $ship,
                    'minimum' => $minimum,
                    'advanced' => $advanced,
                ];

                foreach (['ship' => $ship, 'fit' => $legacyFit, 'minimum' => $minimum, 'advanced' => $advanced] as $key => $passed) {
                    if ($passed) {
                        $data['totals'][$fitting->name][$key]++;
                        $data['totalsByFittingId'][$fittingId][$key]++;
                    }
                }
            }
        }

        $data['totals']['chars'] = $characterSnapshots->count();
        $data['totalsByFittingId']['chars'] = $characterSnapshots->count();

        return $data;
    }

    private function skillMap(array $skills): array
    {
        return collect($skills)
            ->mapWithKeys(function ($skill) {
                return [$skill['typeId'] => $skill['level']];
            })
            ->all();
    }

    private function requirementsFromSkillMap(array $skills): array
    {
        return collect($skills)
            ->map(function ($level, $typeId) {
                return [
                    'typeId' => $typeId,
                    'level' => $level,
                ];
            })
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
