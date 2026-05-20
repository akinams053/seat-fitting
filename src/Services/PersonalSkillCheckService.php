<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingSkillPlan;
use CryptaTech\Seat\Fitting\Models\FittingSkillRequirement;
use Seat\Eveapi\Models\Sde\InvType;

class PersonalSkillCheckService
{
    public function __construct(
        private SkillRequirementCalculator $calculator,
        private CharacterSkillSnapshotService $characters,
    ) {}

    public function checkForCurrentUser(Fitting $fitting): array
    {
        $minimum = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM);
        $advanced = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED);
        $allRequirements = collect($minimum)->merge($advanced)->unique('typeId')->values()->all();
        $characters = $this->characters->addMissingSkills($this->characters->forCurrentUser(), $allRequirements);

        return [
            'skills' => $minimum,
            'requirements' => [
                FittingSkillRequirement::TIER_MINIMUM => $minimum,
                FittingSkillRequirement::TIER_ADVANCED => $advanced,
            ],
            'characters' => $characters->all(),
            'plans' => $this->attachedPlansSummary($fitting),
        ];
    }

    public function checkDoctrineForCurrentUser(Doctrine $doctrine): array
    {
        $fittings = $doctrine->fittings()->with(['ship', 'skillRequirements.skill'])->get();
        $allRequirements = [];
        $fittingResults = [];

        foreach ($fittings as $fitting) {
            $minimum = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM);
            $advanced = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED);
            $requirements = collect($minimum)->merge($advanced)->unique('typeId')->values()->all();
            $allRequirements = collect($allRequirements)->merge($requirements)->unique('typeId')->values()->all();

            $fittingResults[] = [
                'id' => $fitting->fitting_id,
                'name' => $fitting->name,
                'shipType' => $fitting->ship->typeName,
                'typeID' => $fitting->ship_type_id,
                'requirements' => [
                    FittingSkillRequirement::TIER_MINIMUM => $minimum,
                    FittingSkillRequirement::TIER_ADVANCED => $advanced,
                ],
                'plans' => $this->attachedPlansSummary($fitting),
            ];
        }

        $characters = $this->characters->addMissingSkills($this->characters->forCurrentUser(), $allRequirements);

        return [
            'doctrine' => [
                'id' => $doctrine->id,
                'name' => $doctrine->name,
                'plans' => $this->doctrinePlansSummary($doctrine),
            ],
            'fittings' => $fittingResults,
            'characters' => $characters->all(),
        ];
    }

    /**
     * Effective requirements = fitting's own rows MAX-merged with items from any plan
     * attached either directly or via a containing doctrine, restricted to plans of this tier.
     */
    public function effectiveRequirementsForTier(Fitting $fitting, string $tier): array
    {
        $base = $this->requirementsForTier($fitting, $tier);

        return $this->mergePlanItems($fitting, $tier, $base);
    }

    public function requirementsForTier(Fitting $fitting, string $tier): array
    {
        $requirements = $fitting->skillRequirements()
            ->with('skill.group')
            ->where('tier', $tier)
            ->orderBy('skill_type_id')
            ->get();

        if ($requirements->isEmpty() && $tier === FittingSkillRequirement::TIER_MINIMUM) {
            $calculated = collect($this->calculator->calculateForFitting($fitting));
            $skills = InvType::with('group')
                ->whereIn('typeID', $calculated->pluck('typeId')->all())
                ->get()
                ->keyBy('typeID');

            return $calculated
                ->map(function ($skill) use ($tier, $skills) {
                    $type = $skills->get($skill['typeId']);

                    return [
                        'id' => null,
                        'typeId' => $skill['typeId'],
                        'typeName' => $skill['typeName'],
                        'groupId' => $type?->group?->groupID,
                        'groupName' => $type?->group?->groupName,
                        'level' => $skill['level'],
                        'tier' => $tier,
                        'source' => FittingSkillRequirement::SOURCE_CALCULATED,
                        'notes' => null,
                    ];
                })
                ->values()
                ->all();
        }

        return $requirements
            ->map(function (FittingSkillRequirement $requirement) {
                return [
                    'id' => $requirement->id,
                    'typeId' => $requirement->skill_type_id,
                    'typeName' => $requirement->skill->typeName,
                    'groupId' => $requirement->skill->group?->groupID,
                    'groupName' => $requirement->skill->group?->groupName,
                    'level' => $requirement->level,
                    'tier' => $requirement->tier,
                    'source' => $requirement->source,
                    'notes' => $requirement->notes,
                ];
            })
            ->sortBy('typeName')
            ->values()
            ->all();
    }

    private function mergePlanItems(Fitting $fitting, string $tier, array $baseRequirements): array
    {
        $contributionsByTypeId = $this->collectPlanContributions($fitting, $tier);
        if (empty($contributionsByTypeId)) {
            return $baseRequirements;
        }

        $byTypeId = [];
        foreach ($baseRequirements as $req) {
            $byTypeId[(int) $req['typeId']] = $req;
        }

        /* Plans may introduce skills that aren't in the base list. Bulk-load their InvType so
           we can attach typeName / group info without N+1 queries. */
        $newTypeIds = array_diff(array_keys($contributionsByTypeId), array_keys($byTypeId));
        $newTypes = empty($newTypeIds)
            ? collect()
            : InvType::with('group')->whereIn('typeID', $newTypeIds)->get()->keyBy('typeID');

        foreach ($contributionsByTypeId as $typeId => $contributions) {
            $baseLevel = isset($byTypeId[$typeId]) ? (int) $byTypeId[$typeId]['level'] : 0;
            $raisingContributions = array_filter($contributions, fn ($c) => $c['level'] > $baseLevel);
            if (empty($raisingContributions)) {
                /* Plan duplicates or is below the existing level — spec says leave alone. */
                continue;
            }

            $maxLevel = max(array_column($raisingContributions, 'level'));
            $planIds = array_values(array_unique(array_column($raisingContributions, 'plan_id')));

            if (isset($byTypeId[$typeId])) {
                $byTypeId[$typeId]['level'] = $maxLevel;
                $byTypeId[$typeId]['source'] = 'plan';
                $byTypeId[$typeId]['planIds'] = $planIds;
            } else {
                $type = $newTypes->get($typeId);
                $byTypeId[$typeId] = [
                    'id' => null,
                    'typeId' => $typeId,
                    'typeName' => $type?->typeName ?? '',
                    'groupId' => $type?->group?->groupID,
                    'groupName' => $type?->group?->groupName,
                    'level' => $maxLevel,
                    'tier' => $tier,
                    'source' => 'plan',
                    'notes' => null,
                    'planIds' => $planIds,
                ];
            }
        }

        $result = array_values($byTypeId);
        usort($result, fn ($a, $b) => strcasecmp($a['typeName'] ?? '', $b['typeName'] ?? ''));

        return $result;
    }

    /**
     * Build typeId => [{plan_id, level}, ...] from every plan that applies to this fitting:
     * directly attached, plus inherited via any doctrine containing the fitting. Tier-filtered.
     */
    private function collectPlanContributions(Fitting $fitting, string $tier): array
    {
        $plans = $fitting->skillPlans()->where('tier', $tier)->with('items')->get();

        $doctrines = $fitting->doctrines()->with(['skillPlans' => function ($query) use ($tier) {
            $query->where('tier', $tier)->with('items');
        }])->get();

        foreach ($doctrines as $doctrine) {
            foreach ($doctrine->skillPlans as $plan) {
                $plans->push($plan);
            }
        }

        $plans = $plans->unique('id')->values();

        $byTypeId = [];
        foreach ($plans as $plan) {
            foreach ($plan->items as $item) {
                $byTypeId[(int) $item->skill_type_id][] = [
                    'plan_id' => (int) $plan->id,
                    'level' => (int) $item->level,
                ];
            }
        }

        return $byTypeId;
    }

    /**
     * Shape attached-plan summaries for the personal check UI to render cards beside a fitting.
     * Returns plans attached directly to the fitting plus plans inherited from any containing doctrine,
     * tagged with `via` so the UI can distinguish direct vs inherited.
     */
    private function attachedPlansSummary(Fitting $fitting): array
    {
        $summary = [];

        foreach ($fitting->skillPlans()->with('items.skill')->get() as $plan) {
            $summary[] = $this->planSummaryShape($plan, 'fitting', null);
        }

        foreach ($fitting->doctrines()->with(['skillPlans.items.skill'])->get() as $doctrine) {
            foreach ($doctrine->skillPlans as $plan) {
                $summary[] = $this->planSummaryShape($plan, 'doctrine', $doctrine->name);
            }
        }

        /* A plan attached to both the fitting directly and an inherited doctrine should only
           appear once; prefer the more specific 'fitting' source. */
        $seen = [];
        $deduped = [];
        foreach ($summary as $entry) {
            $key = $entry['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $entry;
        }

        return $deduped;
    }

    private function doctrinePlansSummary(Doctrine $doctrine): array
    {
        return $doctrine->skillPlans()->with('items.skill')->get()
            ->map(fn (FittingSkillPlan $plan) => $this->planSummaryShape($plan, 'doctrine', $doctrine->name))
            ->values()
            ->all();
    }

    private function planSummaryShape(FittingSkillPlan $plan, string $via, ?string $viaName): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'tier' => $plan->tier,
            'via' => $via,
            'via_name' => $viaName,
            'items' => $plan->items->map(fn ($item) => [
                'type_id' => (int) $item->skill_type_id,
                'type_name' => $item->skill->typeName ?? '',
                'level' => (int) $item->level,
            ])->values()->all(),
        ];
    }
}
