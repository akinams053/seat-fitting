<?php

namespace CryptaTech\Seat\Fitting\Models;

use Illuminate\Database\Eloquent\Model;

class FittingSkillPlanAttachment extends Model
{
    public $timestamps = true;

    protected $table = 'crypta_tech_seat_fitting_skill_plan_attachments';

    protected $fillable = ['plan_id', 'attachable_type', 'attachable_id', 'scope_doctrine_id'];

    protected $casts = [
        'plan_id' => 'integer',
        'attachable_id' => 'integer',
        'scope_doctrine_id' => 'integer',
    ];

    protected static $unguarded = true;

    public function plan()
    {
        return $this->belongsTo(FittingSkillPlan::class, 'plan_id');
    }
}
