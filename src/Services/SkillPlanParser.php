<?php

namespace CryptaTech\Seat\Fitting\Services;

use Seat\Eveapi\Models\Sde\InvGroup;
use Seat\Eveapi\Models\Sde\InvType;

/**
 * Parses EVE Online skill plan exports into a normalised, resolved item list.
 *
 * Two input forms are accepted line-by-line:
 *   <localized hint="Gunnery">射击学*</localized> 5
 *   Gunnery 5
 *
 * Output: { items: [{type_id, type_name, level}], unmatched: ['raw line', ...] }
 *
 * Duplicates are MAX-collapsed by skill name during parse, then resolved to type IDs
 * against published SDE skills. Unknown names land in `unmatched` so the UI can surface
 * them rather than silently dropping rows.
 */
class SkillPlanParser
{
    public function parse(string $raw): array
    {
        $lines = preg_split('/\R/u', $raw) ?: [];
        $byName = []; // name => max level
        $unmatched = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseLine($line);
            if ($parsed === null) {
                $unmatched[] = $line;
                continue;
            }

            [$name, $level] = $parsed;
            if (! isset($byName[$name]) || $level > $byName[$name]) {
                $byName[$name] = $level;
            }
        }

        if (empty($byName)) {
            return ['items' => [], 'unmatched' => $unmatched];
        }

        $skills = InvType::whereIn('typeName', array_keys($byName))
            ->where('published', true)
            ->whereHas('group', function ($group) {
                $group->where('categoryID', InvGroup::SKILL_CATEGORY_ID)
                    ->where('published', true);
            })
            ->get(['typeID', 'typeName'])
            ->keyBy('typeName');

        $items = [];
        foreach ($byName as $name => $level) {
            $type = $skills->get($name);
            if (! $type) {
                $unmatched[] = $name;
                continue;
            }

            $items[] = [
                'type_id' => (int) $type->typeID,
                'type_name' => $type->typeName,
                'level' => $level,
            ];
        }

        usort($items, fn ($a, $b) => strcasecmp($a['type_name'], $b['type_name']));

        return ['items' => $items, 'unmatched' => $unmatched];
    }

    /**
     * Pull (English-name, level) out of one line, or return null if it doesn't match either form.
     */
    private function parseLine(string $line): ?array
    {
        if (preg_match('/^<localized\s+hint="([^"]+)">[^<]*<\/localized>\s+([1-5])\s*$/u', $line, $m)) {
            $name = $this->normaliseName($m[1]);
            $level = (int) $m[2];

            return $name === '' ? null : [$name, $level];
        }

        if (preg_match('/^(.+?)\s+([1-5])\s*$/u', $line, $m)) {
            $name = $this->normaliseName($m[1]);
            $level = (int) $m[2];

            return $name === '' ? null : [$name, $level];
        }

        return null;
    }

    private function normaliseName(string $raw): string
    {
        return trim(rtrim(trim($raw), '*'));
    }
}
