<?php

namespace CryptaTech\Seat\Fitting\Services;

use Illuminate\Support\Collection;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Sde\DgmTypeAttribute;

class CharacterSkillSnapshotService
{
    public function forCurrentUser(): Collection
    {
        return $this->buildSnapshots(
            CharacterInfo::with('skills')
                ->whereIn('character_id', auth()->user()->associatedCharacterIds())
                ->get()
        );
    }

    public function forCharacters(Collection $characters): Collection
    {
        return $this->buildSnapshots($characters);
    }

    public function addMissingSkills(Collection $snapshots, array $requirements): Collection
    {
        $skillIds = collect($requirements)
            ->pluck('typeId')
            ->unique()
            ->values()
            ->all();

        $ranks = $this->ranksForSkills($skillIds);

        return $snapshots->map(function ($snapshot) use ($skillIds, $ranks) {
            foreach ($skillIds as $skillId) {
                if (isset($snapshot['skill'][$skillId])) {
                    continue;
                }

                $snapshot['skill'][$skillId] = [
                    'level' => 0,
                    'rank' => $ranks[$skillId] ?? 1,
                ];
            }

            return $snapshot;
        });
    }

    public function ranksForSkills(array $skillIds): array
    {
        if (count($skillIds) === 0) {
            return [];
        }

        return DgmTypeAttribute::whereIn('typeID', $skillIds)
            ->where('attributeID', 275)
            ->get()
            ->mapWithKeys(function ($rank) {
                return [$rank->typeID => $rank->valueFloat ?: 1];
            })
            ->all();
    }

    private function buildSnapshots(Collection $characters): Collection
    {
        return $characters->mapWithKeys(function ($character) {
            $snapshot = [
                'id' => $character->character_id,
                'name' => $character->name,
                'skill' => [],
            ];

            foreach ($character->skills as $skill) {
                $snapshot['skill'][$skill->skill_id] = [
                    'level' => $skill->trained_skill_level,
                    'rank' => 1,
                ];
            }

            $ranks = $this->ranksForSkills(array_keys($snapshot['skill']));

            foreach ($snapshot['skill'] as $skillId => $skillData) {
                $snapshot['skill'][$skillId]['rank'] = $ranks[$skillId] ?? 1;
            }

            return [$character->character_id => $snapshot];
        });
    }
}
