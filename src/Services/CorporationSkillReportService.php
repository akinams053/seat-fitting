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

        /* Doctrine-scoped report: pass doctrineId so plans attached to this doctrine apply,
           but plans attached to OTHER doctrines containing the same fittings do not bleed in. */
        return $this->runForFittings($allianceIds, $corporationIds, $doctrine->fittings, $doctrine->id);
    }

    public function runForFitting(array $allianceIds, array $corporationIds, int $fittingId): array
    {
        $fitting = Fitting::with('ship')->where('fitting_id', $fittingId)->firstOrFail();

        /* Single-fitting report has no doctrine context: only directly attached plans apply. */
        return $this->runForFittings($allianceIds, $corporationIds, collect([$fitting]), null);
    }

    public function runForFittings(array $allianceIds, array $corporationIds, Collection $fittings, ?int $contextDoctrineId = null): array
    {
        $fittingChecks = [];
        $allRequirements = [];

        foreach ($fittings as $fitting) {
            $shipSkills = $this->skillMap($this->calculator->calculateForTypeId($fitting->ship_type_id));
            $minimumList = $this->personalSkillCheck->effectiveRequirementsForTier($fitting, 'minimum', $contextDoctrineId);
            $advancedList = $this->personalSkillCheck->effectiveRequirementsForTier($fitting, 'advanced', $contextDoctrineId);
            /* Apply the same advanced-≥-minimum normalization the personal-check path uses,
               so corp report and personal check stay in lockstep about what "advanced met" means. */
            $advancedList = $this->personalSkillCheck->normalizeAdvancedAgainstMinimum($minimumList, $advancedList);
            $minimumSkills = $this->skillMap($minimumList);
            $advancedSkills = $this->skillMap($advancedList);

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
            ->whereIn('character_id', RefreshToken::query()->select('character_id'))
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
     * Characters without an active RefreshToken should normally be filtered out before aggregation.
     * The orphan fallback remains as a guard for races or inconsistent data.
     */
    private function aggregateByUser(Collection $characterSnapshots, Collection $fittings, array $charsById): array
    {
        $characterIds = $characterSnapshots->keys()->all();
        $tokens = RefreshToken::whereIn('character_id', $characterIds)
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
