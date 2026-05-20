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
 *
 * Idempotent: every step checks current schema state, so re-runs after partial failure
 * (MySQL ALTER TABLE is auto-committed and non-transactional) just no-op the parts that
 * already happened.
 */
return new class extends Migration
{
    public function up()
    {
        $table = 'crypta_tech_seat_fitting_skill_plan_attachments';

        if (! Schema::hasColumn($table, 'scope_doctrine_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('scope_doctrine_id')->nullable()->after('attachable_id');
            });
        }

        if (! $this->indexExists($table, 'ctsf_plan_attach_scope_index')) {
            Schema::table($table, function (Blueprint $t) {
                $t->index('scope_doctrine_id', 'ctsf_plan_attach_scope_index');
            });
        }

        /* The old 3-column UNIQUE doubles as the only index on plan_id, so dropping it would
           leave the plan_id FK without an index. Add a single-column plan_id index FIRST so
           the FK has somewhere to fall back to, then we can safely drop/recreate the unique. */
        if (! $this->indexExists($table, 'ctsf_plan_attach_plan_index')) {
            Schema::table($table, function (Blueprint $t) {
                $t->index('plan_id', 'ctsf_plan_attach_plan_index');
            });
        }

        if ($this->indexExists($table, 'ctsf_plan_attach_unique') &&
            ! $this->uniqueCoversFourColumns($table, 'ctsf_plan_attach_unique')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique('ctsf_plan_attach_unique');
            });
        }

        if (! $this->indexExists($table, 'ctsf_plan_attach_unique')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unique(
                    ['plan_id', 'attachable_type', 'attachable_id', 'scope_doctrine_id'],
                    'ctsf_plan_attach_unique'
                );
            });
        }

        /* Drop ambiguous per-fit attachments left over from 1.4.0 (user confirmed). */
        DB::table($table)
            ->where('attachable_type', 'fitting')
            ->whereNull('scope_doctrine_id')
            ->delete();
    }

    public function down()
    {
        $table = 'crypta_tech_seat_fitting_skill_plan_attachments';

        Schema::table($table, function (Blueprint $t) {
            $t->dropUnique('ctsf_plan_attach_unique');
        });

        Schema::table($table, function (Blueprint $t) {
            $t->unique(['plan_id', 'attachable_type', 'attachable_id'], 'ctsf_plan_attach_unique');
            $t->dropIndex('ctsf_plan_attach_scope_index');
            $t->dropIndex('ctsf_plan_attach_plan_index');
            $t->dropColumn('scope_doctrine_id');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$indexName]);

        return count($rows) > 0;
    }

    private function uniqueCoversFourColumns(string $table, string $indexName): bool
    {
        $rows = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ? ORDER BY Seq_in_index", [$indexName]);

        return count($rows) === 4;
    }
};
