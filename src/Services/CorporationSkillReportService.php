<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Doctrine;
use CryptaTech\Seat\Fitting\Models\Fitting;
use Illuminate\Support\Collection;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Web\Models\User;

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

        $data['usersById'] = $this->aggregateByUser($characterSnapshots, $fittings, $data['charsById']);

        return $data;
    }

    /**
     * Group character results by SeAT user (account) so the report can collapse alts under their main.
     *
     * Characters with no RefreshToken row (never registered in SeAT) become single-character pseudo-users
     * keyed by character_id so they still show up in the table.
     */
    private function aggregateByUser(Collection $characterSnapshots, Collection $fittings, array $charsById): array
    {
        $characterIds = $characterSnapshots->keys()->all();
        /* withTrashed: SeAT soft-deletes RefreshToken when an SSO authorisation expires or scopes
           shrink. The user → character link is still meaningful for aggregation even though the
           token can no longer refresh — the cached CharacterSkill snapshot is what we read here,
           not live API data. Without withTrashed every once-registered-but-now-expired character
           gets treated as an orphan, which on a real corp inflates the user count ~10×. */
        $tokens = RefreshToken::withTrashed()->whereIn('character_id', $characterIds)
            ->select('character_id', 'user_id')
            ->get()
            ->keyBy('character_id');

        $userIds = $tokens->pluck('user_id')->unique()->values()->all();
        $users = User::whereIn('id', $userIds)
            ->select('id', 'main_character_id')
            ->get()
            ->keyBy('id');

        $mainCharIds = $users->pluck('main_character_id')->filter()->unique()->values()->all();
        $mainCharInfo = CharacterInfo::whereIn('character_id', $mainCharIds)
            ->select('character_id', 'name', 'title')
            ->get()
            ->keyBy('character_id');

        $usersById = [];

        foreach ($characterSnapshots as $characterId => $character) {
            $token = $tokens->get($characterId);
            $userKey = $token ? 'user:'.$token->user_id : 'orphan:'.$characterId;

            if (! isset($usersById[$userKey])) {
                if ($token) {
                    $user = $users->get($token->user_id);
                    $mainCharId = $user?->main_character_id;
                    $mainChar = $mainCharId ? $mainCharInfo->get($mainCharId) : null;
                    $usersById[$userKey] = [
                        'user_id' => $token->user_id,
                        'is_orphan' => false,
                        'main_character_id' => $mainCharId,
                        'main_character_name' => $mainChar?->name ?? $character['name'],
                        'main_character_title' => $mainChar?->title ?? $character['title'],
                        'characters' => [],
                        'fittings' => [],
                    ];
                } else {
                    $usersById[$userKey] = [
                        'user_id' => null,
                        'is_orphan' => true,
                        'main_character_id' => $characterId,
                        'main_character_name' => $character['name'],
                        'main_character_title' => $character['title'],
                        'characters' => [],
                        'fittings' => [],
                    ];
                }
            }

            $usersById[$userKey]['characters'][$characterId] = [
                'character_id' => $characterId,
                'name' => $character['name'],
                'title' => $character['title'],
                'is_main' => $characterId == $usersById[$userKey]['main_character_id'],
                'fittings' => $charsById[$characterId]['fittings'] ?? [],
            ];
        }

        /* Per-user aggregation across alts: max status + count of alts at that status. */
        foreach ($usersById as &$u) {
            $altCount = count($u['characters']);
            foreach ($fittings as $fitting) {
                $fittingId = $fitting->fitting_id;
                $hasAdvancedConfig = null;
                $minCount = 0;
                $advCount = 0;

                foreach ($u['characters'] as $char) {
                    $check = $char['fittings'][$fittingId] ?? null;
                    if (! $check) {
                        continue;
                    }
                    if ($check['minimum']) {
                        $minCount++;
                    }
                    if ($check['advanced'] === true) {
                        $advCount++;
                    }
                    if ($check['advanced'] !== null) {
                        $hasAdvancedConfig = true;
                    }
                }

                $u['fittings'][$fittingId] = [
                    'character_count' => $altCount,
                    'minimum_count' => $minCount,
                    'advanced_count' => $hasAdvancedConfig ? $advCount : null,
                    'minimum' => $minCount > 0,
                    'advanced' => $hasAdvancedConfig ? ($advCount > 0) : null,
                ];
            }
        }
        unset($u);

        /* Sort by main character name (case-insensitive) for stable, alphabetical display. */
        uasort($usersById, fn ($a, $b) => strcasecmp($a['main_character_name'] ?? '', $b['main_character_name'] ?? ''));

        return $usersById;
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
