<?php

namespace CryptaTech\Seat\Fitting\Services;

use CryptaTech\Seat\Fitting\Models\Translation;
use Illuminate\Support\Facades\App;

/**
 * Looks up CCP-official localized names for SDE entities (types, groups,
 * categories, market groups) from `crypta_tech_seat_translations`, and
 * provides in-place substitution helpers so callers can keep their existing
 * result-array shapes — they just call `apply*Names($rows)` after building.
 *
 * English ('en') is the canonical identifier: EFT parsing, search-by-name
 * lookups, and any column-level WHERE typeName='...' query MUST continue
 * hitting the English `invTypes.typeName` directly. Localization only
 * substitutes display strings on the way out to the UI.
 */
class LocalizationService
{
    /** Per-request memoization. Keys are "<source>:<id>". null = no row. */
    private array $cache = [];

    public function currentLocale(): string
    {
        return App::getLocale();
    }

    /** Skip the lookup entirely on 'en' — English is what's already in InvType / InvGroup. */
    public function shouldLocalize(): bool
    {
        return $this->currentLocale() !== 'en';
    }

    /**
     * Batch lookup `[id, ...]` → `[id => localized_name, ...]`. Missing entries
     * are absent from the result (caller falls back to whatever English name
     * it already has). Uses the request-scoped cache to dedupe repeat lookups.
     */
    public function namesFor(string $source, iterable $ids): array
    {
        if (! $this->shouldLocalize()) {
            return [];
        }

        $needed = [];
        $hits = [];

        foreach ($ids as $id) {
            if ($id === null) {
                continue;
            }
            $intId = (int) $id;
            if ($intId <= 0) {
                continue;
            }
            $key = $source.':'.$intId;
            if (array_key_exists($key, $this->cache)) {
                if ($this->cache[$key] !== null) {
                    $hits[$intId] = $this->cache[$key];
                }
            } else {
                $needed[$intId] = true;
            }
        }

        if (! empty($needed)) {
            $rows = Translation::query()
                ->where('source', $source)
                ->where('locale', $this->currentLocale())
                ->whereIn('source_id', array_keys($needed))
                ->pluck('name', 'source_id')
                ->all();
            foreach (array_keys($needed) as $id) {
                $name = $rows[$id] ?? null;
                $this->cache[$source.':'.$id] = $name;
                if ($name !== null) {
                    $hits[$id] = $name;
                }
            }
        }

        return $hits;
    }

    public function typeNames(iterable $ids): array
    {
        return $this->namesFor(Translation::SOURCE_INV_TYPES, $ids);
    }

    /** Single-id convenience: returns the localized name if available, else `$fallback`. */
    public function typeName(?int $id, ?string $fallback = null): ?string
    {
        if ($id === null) {
            return $fallback;
        }
        $map = $this->typeNames([$id]);

        return $map[$id] ?? $fallback;
    }

    public function groupNames(iterable $ids): array
    {
        return $this->namesFor(Translation::SOURCE_INV_GROUPS, $ids);
    }

    public function categoryNames(iterable $ids): array
    {
        return $this->namesFor(Translation::SOURCE_INV_CATEGORIES, $ids);
    }

    public function marketGroupNames(iterable $ids): array
    {
        return $this->namesFor(Translation::SOURCE_INV_MARKET_GROUPS, $ids);
    }

    /**
     * In-place name substitution for an array of result rows. For each row,
     * `$idKey` identifies the type_id and `$nameKey` is the column to overwrite
     * if a localized name exists.
     */
    public function applyTypeNames(array &$rows, string $idKey = 'typeId', string $nameKey = 'typeName'): void
    {
        $this->applyNamesGeneric($rows, $idKey, $nameKey, fn ($ids) => $this->typeNames($ids));
    }

    public function applyGroupNames(array &$rows, string $idKey = 'groupId', string $nameKey = 'groupName'): void
    {
        $this->applyNamesGeneric($rows, $idKey, $nameKey, fn ($ids) => $this->groupNames($ids));
    }

    private function applyNamesGeneric(array &$rows, string $idKey, string $nameKey, callable $resolver): void
    {
        if (! $this->shouldLocalize() || empty($rows)) {
            return;
        }
        $ids = [];
        foreach ($rows as $row) {
            if (isset($row[$idKey]) && $row[$idKey] !== null) {
                $ids[] = (int) $row[$idKey];
            }
        }
        if (empty($ids)) {
            return;
        }
        $map = $resolver(array_unique($ids));
        if (empty($map)) {
            return;
        }
        foreach ($rows as &$row) {
            $id = $row[$idKey] ?? null;
            if ($id !== null && isset($map[(int) $id])) {
                $row[$nameKey] = $map[(int) $id];
            }
        }
        unset($row);
    }

    /**
     * Locale-aware sort of an array of dicts by `$nameKey`. Uses ICU Collator
     * (`zh_CN` etc.) when ext-intl is available; falls back to byte-wise strcmp
     * otherwise, which yields a consistent — if not pinyin-semantic — order.
     */
    public function sortByLocalizedName(array &$rows, string $nameKey = 'typeName'): void
    {
        if ($this->shouldLocalize() && class_exists(\Collator::class)) {
            $collator = \Collator::create(str_replace('-', '_', $this->currentLocale()));
            if ($collator) {
                usort($rows, fn ($a, $b) => $collator->compare(
                    (string) ($a[$nameKey] ?? ''),
                    (string) ($b[$nameKey] ?? '')
                ));

                return;
            }
        }
        usort($rows, fn ($a, $b) => strcmp((string) ($a[$nameKey] ?? ''), (string) ($b[$nameKey] ?? '')));
    }
}
