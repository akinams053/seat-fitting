<?php

namespace CryptaTech\Seat\Fitting\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\InvType;

class FittingSkillPlanItem extends Model
{
    public $timestamps = true;

    protected $table = 'crypta_tech_seat_fitting_skill_plan_items';

    protected $fillable = ['plan_id', 'skill_type_id', 'level'];

    protected $casts = [
        'level' => 'integer',
        'skill_type_id' => 'integer',
        'plan_id' => 'integer',
    ];

    protected static $unguarded = true;

    public function plan()
    {
        return $this->belongsTo(FittingSkillPlan::class, 'plan_id');
    }

    public function skill()
    {
        return $this->belongsTo(InvType::class, 'skill_type_id', 'typeID')
            ->withDefault([
                'typeName' => trans('web::seat.unknown'),
            ]);
    }
}
