<?php

namespace CryptaTech\Seat\Fitting\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\InvType;

class FittingSkillRequirement extends Model
{
    const TIER_MINIMUM = 'minimum';

    const TIER_ADVANCED = 'advanced';

    const SOURCE_CALCULATED = 'calculated';

    const SOURCE_MANUAL = 'manual';

    const SOURCE_CUSTOM = 'custom';

    protected static $unguarded = true;

    protected $table = 'crypta_tech_seat_fitting_skill_requirements';

    protected $fillable = [
        'fitting_id',
        'skill_type_id',
        'tier',
        'level',
        'source',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'skill_type_id' => 'integer',
    ];

    public function fitting()
    {
        return $this->belongsTo(Fitting::class, 'fitting_id', 'fitting_id');
    }

    public function skill()
    {
        return $this->belongsTo(InvType::class, 'skill_type_id', 'typeID')
            ->withDefault([
                'typeName' => trans('web::seat.unknown'),
            ]);
    }
}
