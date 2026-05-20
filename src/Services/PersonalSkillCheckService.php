<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
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
        $minimum = $this->requirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM);
        $advanced = $this->requirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED);
        $allRequirements = collect($minimum)->merge($advanced)->unique('typeId')->values()->all();
        $characters = $this->characters->addMissingSkills($this->characters->forCurrentUser(), $allRequirements);

        return [
            'skills' => $minimum,
            'requirements' => [
                FittingSkillRequirement::TIER_MINIMUM => $minimum,
                FittingSkillRequirement::TIER_ADVANCED => $advanced,
            ],
            'characters' => $characters->all(),
        ];
    }

    public function checkDoctrineForCurrentUser(Doctrine $doctrine): array
    {
        $fittings = $doctrine->fittings()->with(['ship', 'skillRequirements.skill'])->get();
        $allRequirements = [];
        $fittingResults = [];

        foreach ($fittings as $fitting) {
            $minimum = $this->requirementsForTier($fitting, FittingSkillRequirement::TIER_MINIMUM);
            $advanced = $this->requirementsForTier($fitting, FittingSkillRequirement::TIER_ADVANCED);
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
            ];
        }

        $characters = $this->characters->addMissingSkills($this->characters->forCurrentUser(), $allRequirements);

        return [
            'doctrine' => [
                'id' => $doctrine->id,
                'name' => $doctrine->name,
            ],
            'fittings' => $fittingResults,
            'characters' => $characters->all(),
        ];
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
}
