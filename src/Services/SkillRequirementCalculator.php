<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Helpers\CalculateConstants;
use CryptaTech\Seat\Fitting\Models\Fitting;
use CryptaTech\Seat\Fitting\Models\FittingItem;
use Seat\Eveapi\Models\Sde\DgmTypeAttribute;
use Seat\Eveapi\Models\Sde\InvType;

class SkillRequirementCalculator implements CalculateConstants
{
    public function __construct(private LocalizationService $localization) {}

    public function calculateForFitting(Fitting $fitting): array
    {
        $types = collect($fitting->fitItems->all())
            ->map(function (FittingItem $item) {
                return $item->type_id;
            })
            ->push($fitting->ship_type_id)
            ->unique()
            ->values()
            ->all();

        return $this->formatSkills($this->calculateForTypeIds($types));
    }

    public function calculateForTypeId(int $typeId): array
    {
        return $this->formatSkills($this->calculateForTypeIds([$typeId]));
    }

    public function calculateForTypeIds(iterable $typeIds): array
    {
        $requiredSkills = [];
        $visitedTypes = [];

        $this->collectRequiredSkills($typeIds, $requiredSkills, $visitedTypes);

        return $requiredSkills;
    }

    public function formatSkills(array $skills): array
    {
        $typeNames = InvType::whereIn('typeID', array_keys($skills))
            ->pluck('typeName', 'typeID');

        $formatted = [];

        foreach ($skills as $skillId => $level) {
            $formatted[] = [
                'typeId' => (int) $skillId,
                'typeName' => $typeNames[$skillId] ?? trans('web::seat.unknown'),
                'level' => (int) $level,
            ];
        }

        $this->localization->applyTypeNames($formatted);
        $this->localization->sortByLocalizedName($formatted);

        return $formatted;
    }

    private function collectRequiredSkills(iterable $typeIds, array &$requiredSkills, array &$visitedTypes): void
    {
        $attributeIds = array_merge(array_keys(self::REQ_SKILLS_ATTR_LEVELS), array_values(self::REQ_SKILLS_ATTR_LEVELS));

        foreach ($typeIds as $typeId) {
            if (is_array($typeId)) {
                $typeId = $typeId['typeID'];
            }

            $typeId = (int) $typeId;

            if (isset($visitedTypes[$typeId])) {
                continue;
            }

            $visitedTypes[$typeId] = true;

            $attributes = DgmTypeAttribute::where('typeID', $typeId)
                ->whereIn('attributeID', $attributeIds)
                ->get();

            if ($attributes->isEmpty()) {
                continue;
            }

            $skillsToAdd = $this->prepareRequiredSkills($attributes);

            foreach ($skillsToAdd as $skillId => $level) {
                if (! isset($requiredSkills[$skillId]) || $requiredSkills[$skillId] < $level) {
                    $requiredSkills[$skillId] = $level;
                }
            }

            $this->collectRequiredSkills(array_keys($skillsToAdd), $requiredSkills, $visitedTypes);
        }
    }

    private function prepareRequiredSkills($attributes): array
    {
        $skills = [];
        $keys = [];

        foreach ($attributes as $attribute) {
            $attributeValue = $attribute['valueInt'] !== null ? $attribute['valueInt'] : $attribute['valueFloat'];
            $keys[$attribute['attributeID']] = $attributeValue;
        }

        foreach (self::REQ_SKILLS_ATTR_LEVELS as $skillAttribute => $levelAttribute) {
            if (isset($keys[$skillAttribute], $keys[$levelAttribute])) {
                $skills[(int) $keys[$skillAttribute]] = (int) $keys[$levelAttribute];
            }
        }

        return $skills;
    }
}
