<?php

namespace CryptaTech\Seat\Fitting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plugin-owned localized name lookup for SDE entities (types, groups, categories,
 * market groups). Imported from CCP SDE YAML via `cryptatech:fittings:import-translations`.
 * Joined by display-layer services to substitute English `typeName` / `groupName` with
 * the current user locale's name where available; English remains the canonical
 * identifier (EFT parsing, search, etc.) — see CLAUDE.md.
 */
class Translation extends Model
{
    const SOURCE_INV_TYPES = 'invTypes';

    const SOURCE_INV_GROUPS = 'invGroups';

    const SOURCE_INV_CATEGORIES = 'invCategories';

    const SOURCE_INV_MARKET_GROUPS = 'invMarketGroups';

    const VALID_SOURCES = [
        self::SOURCE_INV_TYPES,
        self::SOURCE_INV_GROUPS,
        self::SOURCE_INV_CATEGORIES,
        self::SOURCE_INV_MARKET_GROUPS,
    ];

    public $timestamps = false;

    protected $table = 'crypta_tech_seat_translations';

    protected $fillable = ['source', 'source_id', 'locale', 'name'];

    protected $casts = [
        'source_id' => 'integer',
    ];
}
