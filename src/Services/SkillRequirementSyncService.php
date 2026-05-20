<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingSkillRequirement;

class SkillRequirementSyncService
{
    public function __construct(private SkillRequirementCalculator $calculator) {}

    public function syncCalculatedMinimumRequirements(Fitting $fitting): void
    {
        $calculated = collect($this->calculator->calculateForFitting($fitting));
        $calculatedSkillIds = $calculated->pluck('typeId')->map(function ($typeId) {
            return (int) $typeId;
        })->all();

        foreach ($calculated as $skill) {
            $requirement = FittingSkillRequirement::where('fitting_id', $fitting->fitting_id)
                ->where('skill_type_id', $skill['typeId'])
                ->where('tier', FittingSkillRequirement::TIER_MINIMUM)
                ->first();

            if ($requirement && $requirement->source !== FittingSkillRequirement::SOURCE_CALCULATED) {
                continue;
            }

            if (! $requirement) {
                $requirement = new FittingSkillRequirement;
                $requirement->fitting_id = $fitting->fitting_id;
                $requirement->skill_type_id = $skill['typeId'];
                $requirement->tier = FittingSkillRequirement::TIER_MINIMUM;
                $requirement->source = FittingSkillRequirement::SOURCE_CALCULATED;
            }

            $requirement->level = $skill['level'];
            $requirement->is_active = true;
            $requirement->save();
        }

        FittingSkillRequirement::where('fitting_id', $fitting->fitting_id)
            ->where('tier', FittingSkillRequirement::TIER_MINIMUM)
            ->where('source', FittingSkillRequirement::SOURCE_CALCULATED)
            ->whereNotIn('skill_type_id', $calculatedSkillIds)
            ->delete();
    }

    public function replaceRequirements(Fitting $fitting, string $tier, array $requirements): void
    {
        $seen = [];

        foreach ($requirements as $requirementData) {
            if (! isset($requirementData['skill_type_id'], $requirementData['level'])) {
                continue;
            }

            $skillTypeId = (int) $requirementData['skill_type_id'];
            $level = (int) $requirementData['level'];

            if ($skillTypeId <= 0 || $level < 1 || $level > 5) {
                continue;
            }

            $seen[] = $skillTypeId;

            FittingSkillRequirement::updateOrCreate(
                [
                    'fitting_id' => $fitting->fitting_id,
                    'skill_type_id' => $skillTypeId,
                    'tier' => $tier,
                ],
                [
                    'level' => $level,
                    'source' => $requirementData['source'] ?? FittingSkillRequirement::SOURCE_MANUAL,
                    'is_active' => true,
                    'notes' => $requirementData['notes'] ?? null,
                ]
            );
        }

        FittingSkillRequirement::where('fitting_id', $fitting->fitting_id)
            ->where('tier', $tier)
            ->when(count($seen) > 0, function ($query) use ($seen) {
                $query->whereNotIn('skill_type_id', $seen);
            })
            ->when(count($seen) === 0, function ($query) {
                $query->whereRaw('1 = 1');
            })
            ->delete();
    }
}
