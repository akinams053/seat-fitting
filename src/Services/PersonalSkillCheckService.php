<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingSkillPlan;
use CryptaTech\Seat\Fitting\Models\FittingSkillPlanAttachment;
use CryptaTech\Seat\Fitting\Models\FittingSkillRequirement;
use Seat\Eveapi\Models\Sde\InvType;

class PersonalSkillCheckService
{
    public function __construct(
        private SkillRequirementCalculator $calculator,
        private CharacterSkillSnapshotService $characters,
    ) {}

    public function checkForCurrentUser(Fitting $fitting, ?int $contextDoctrineId = null): array
    {
        /* Personal single-fit check now carries the doctrine context that the user clicked the
           fit through (each tree row knows which group it represents). With context, plans behave
           identically to a group check at this fit: per-fit attach scope=D + universal scope=NULL
           + D's doctrine attach. Without context (ungrouped fit), only universal direct attaches
           and no doctrine inheritance — keeps fit-in-D and fit-in-D2 cleanly independent. */
        $minimum = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM, $contextDoctrineId);
        $advanced = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED, $contextDoctrineId);
        $allRequirements = collect($minimum)->merge($advanced)->unique('typeId')->values()->all();
        $characters = $this->characters->addMissingSkills($this->characters->forCurrentUser(), $allRequirements);

        return [
            'skills' => $minimum,
            'requirements' => [
                FittingSkillRequirement::TIER_MINIMUM => $minimum,
                FittingSkillRequirement::TIER_ADVANCED => $advanced,
            ],
            'characters' => $characters->all(),
            'plans' => $this->attachedPlansSummary($fitting, $contextDoctrineId),
        ];
    }

    public function checkDoctrineForCurrentUser(Doctrine $doctrine): array
    {
        $fittings = $doctrine->fittings()->with(['ship', 'skillRequirements.skill'])->get();
        $allRequirements = [];
        $fittingResults = [];

        foreach ($fittings as $fitting) {
            $minimum = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM, $doctrine->id);
            $advanced = $this->effectiveRequirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED, $doctrine->id);
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
                'plans' => $this->attachedPlansSummary($fitting, $doctrine->id),
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
     * Effective requirements = fitting's own rows MAX-merged with applicable plan items.
     *
     * Strict scope semantics:
     *   - Direct fit attachments: scope NULL (universal) always; scope = $contextDoctrineId
     *     when context is set. Per-fit attachments scoped to OTHER doctrines never bleed in
     *     just because the fit is also a member of those.
     *   - Doctrine attachments: only $contextDoctrineId's. No context means no doctrine
     *     inheritance — single-fit personal check of an ungrouped fit shows only its
     *     universal direct attaches (which should be empty in normal use).
     *
     * Personal/management pages always pass the doctrine the user clicked the fit
     * THROUGH (each tree row knows its group). Group/corp checks pass the active doctrine.
     */
    public function effectiveRequirementsForTier(Fitting $fitting, string $tier, ?int $contextDoctrineId = null): array
    {
        $base = $this->requirementsForTier($fitting, $tier);

        return $this->mergePlanItems($fitting, $tier, $base, $contextDoctrineId);
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

    private function mergePlanItems(Fitting $fitting, string $tier, array $baseRequirements, ?int $contextDoctrineId): array
    {
        $contributionsByTypeId = $this->collectPlanContributions($fitting, $tier, $contextDoctrineId);
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
     * Build typeId => [{plan_id, level}, ...] from every plan applicable in the given context.
     *
     * Strict scope:
     *   - Direct fit attachments: scope NULL (universal) + scope = $contextDoctrineId.
     *     Per-fit attachments scoped to other doctrines are excluded even if the fit is a
     *     member there — those only apply in their own doctrine context.
     *   - Doctrine attachments: only $contextDoctrineId. Without context, no doctrine
     *     inheritance is added.
     * Tier-filtered. De-dupes plans by id.
     */
    private function collectPlanContributions(Fitting $fitting, string $tier, ?int $contextDoctrineId): array
    {
        $directPlanIds = $this->relevantDirectAttachments($fitting, $contextDoctrineId)
            ->pluck('plan_id')
            ->unique();

        $plans = FittingSkillPlan::with('items')
            ->whereIn('id', $directPlanIds)
            ->where('tier', $tier)
            ->get();

        if ($contextDoctrineId !== null) {
            $doctrine = Doctrine::with(['skillPlans' => function ($query) use ($tier) {
                $query->where('tier', $tier)->with('items');
            }])->find($contextDoctrineId);

            if ($doctrine) {
                foreach ($doctrine->skillPlans as $plan) {
                    $plans->push($plan);
                }
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
     * Plan-attachment summary for the UI.
     *
     * Same strict scope as collectPlanContributions:
     *   - Direct fit attachments: scope NULL + scope = $contextDoctrineId.
     *   - Doctrine attachments: only $contextDoctrineId.
     * A plan that appears via multiple paths is reported once.
     */
    private function attachedPlansSummary(Fitting $fitting, ?int $contextDoctrineId): array
    {
        $summary = [];

        $direct = $this->relevantDirectAttachments($fitting, $contextDoctrineId);
        $directPlanIds = $direct->pluck('plan_id')->unique();
        $directPlans = FittingSkillPlan::with('items.skill')->whereIn('id', $directPlanIds)->get()->keyBy('id');
        $scopedDoctrineIds = $direct->pluck('scope_doctrine_id')->filter()->unique()->values()->all();
        $scopedDoctrineNames = empty($scopedDoctrineIds)
            ? collect()
            : Doctrine::whereIn('id', $scopedDoctrineIds)->pluck('name', 'id');

        foreach ($direct as $row) {
            $plan = $directPlans->get($row->plan_id);
            if (! $plan) {
                continue;
            }
            $viaName = $row->scope_doctrine_id ? ($scopedDoctrineNames[$row->scope_doctrine_id] ?? null) : null;
            $summary[] = $this->planSummaryShape($plan, 'fitting', $viaName);
        }

        if ($contextDoctrineId !== null) {
            $doctrine = Doctrine::with(['skillPlans.items.skill'])->find($contextDoctrineId);
            if ($doctrine) {
                foreach ($doctrine->skillPlans as $plan) {
                    $summary[] = $this->planSummaryShape($plan, 'doctrine', $doctrine->name);
                }
            }
        }

        /* Dedupe by plan id; prefer the 'fitting' entry over 'doctrine' if both exist (more specific). */
        $byId = [];
        foreach ($summary as $entry) {
            if (! isset($byId[$entry['id']])) {
                $byId[$entry['id']] = $entry;
            } elseif ($byId[$entry['id']]['via'] === 'doctrine' && $entry['via'] === 'fitting') {
                $byId[$entry['id']] = $entry;
            }
        }

        return array_values($byId);
    }

    /**
     * Fetch direct (attachable_type=fitting) attachments matching the strict scope:
     * scope NULL (universal) plus scope = $contextDoctrineId (if set). Per-fit attachments
     * scoped to other doctrines are deliberately excluded — they only apply in their own
     * context, never via "the fit happens to also be in those doctrines".
     */
    private function relevantDirectAttachments(Fitting $fitting, ?int $contextDoctrineId)
    {
        $query = FittingSkillPlanAttachment::where('attachable_type', FittingSkillPlan::ATTACHABLE_FITTING)
            ->where('attachable_id', $fitting->fitting_id);

        if ($contextDoctrineId !== null) {
            $query->where(function ($q) use ($contextDoctrineId) {
                $q->whereNull('scope_doctrine_id')->orWhere('scope_doctrine_id', $contextDoctrineId);
            });
        } else {
            $query->whereNull('scope_doctrine_id');
        }

        return $query->get(['plan_id', 'scope_doctrine_id']);
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
