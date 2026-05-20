<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope per-fit plan attachments to the doctrine they were attached through.
 *
 * Before: an attachment row (plan, fitting) applied to that fitting in every doctrine
 * containing it. Putting plan P on fit F via group D's drop zone also affected F in D2.
 *
 * After: per-fit attachments carry scope_doctrine_id. The check / report layer filters
 * by (scope IS NULL OR scope = current_doctrine), so D's attach no longer leaks to D2.
 * Doctrine-direct attachments keep scope NULL.
 *
 * Existing 1.4.0-era per-fit attachments are dropped — they were created without a
 * doctrine context and are ambiguous to migrate. Users re-attach in the new model.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('crypta_tech_seat_fitting_skill_plan_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('scope_doctrine_id')->nullable()->after('attachable_id');
            $table->index('scope_doctrine_id', 'ctsf_plan_attach_scope_index');
        });

        Schema::table('crypta_tech_seat_fitting_skill_plan_attachments', function (Blueprint $table) {
            /* Drop the old UNIQUE so the new four-column UNIQUE can replace it. */
            $table->dropUnique('ctsf_plan_attach_unique');
        });

        Schema::table('crypta_tech_seat_fitting_skill_plan_attachments', function (Blueprint $table) {
            $table->unique(
                ['plan_id', 'attachable_type', 'attachable_id', 'scope_doctrine_id'],
                'ctsf_plan_attach_unique'
            );
        });

        /* Drop ambiguous per-fit attachments left over from 1.4.0 (user confirmed). */
        DB::table('crypta_tech_seat_fitting_skill_plan_attachments')
            ->where('attachable_type', 'fitting')
            ->whereNull('scope_doctrine_id')
            ->delete();
    }

    public function down()
    {
        Schema::table('crypta_tech_seat_fitting_skill_plan_attachments', function (Blueprint $table) {
            $table->dropUnique('ctsf_plan_attach_unique');
        });

        Schema::table('crypta_tech_seat_fitting_skill_plan_attachments', function (Blueprint $table) {
            $table->unique(['plan_id', 'attachable_type', 'attachable_id'], 'ctsf_plan_attach_unique');
            $table->dropIndex('ctsf_plan_attach_scope_index');
            $table->dropColumn('scope_doctrine_id');
        });
    }
};
