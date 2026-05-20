<?php

namespace CryptaTech\Seat\Fitting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class FittingSkillPlan extends Model
{
    const TIER_MINIMUM = 'minimum';

    const TIER_ADVANCED = 'advanced';

    const ATTACHABLE_FITTING = 'fitting';

    const ATTACHABLE_DOCTRINE = 'doctrine';

    public $timestamps = true;

    protected $table = 'crypta_tech_seat_fitting_skill_plans';

    protected $fillable = ['name', 'description', 'tier'];

    protected static $unguarded = true;

    public function items(): HasMany
    {
        return $this->hasMany(FittingSkillPlanItem::class, 'plan_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FittingSkillPlanAttachment::class, 'plan_id');
    }

    public function fittings()
    {
        return $this->belongsToMany(
            Fitting::class,
            'crypta_tech_seat_fitting_skill_plan_attachments',
            'plan_id',
            'attachable_id'
        )->wherePivot('attachable_type', self::ATTACHABLE_FITTING);
    }

    public function doctrines()
    {
        return $this->belongsToMany(
            Doctrine::class,
            'crypta_tech_seat_fitting_skill_plan_attachments',
            'plan_id',
            'attachable_id'
        )->wherePivot('attachable_type', self::ATTACHABLE_DOCTRINE);
    }
}
