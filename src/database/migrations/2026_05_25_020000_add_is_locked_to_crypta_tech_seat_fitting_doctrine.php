<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-doctrine lock flag. When set, the workspace and HTTP layer reject all mutations on
 * the doctrine (rename / delete / fitting add-remove / plan attach-detach at group or
 * per-fit scope) until an authorised user (fitting.lock_doctrine) flips it back. Reads
 * (personal & corp checks) are unaffected.
 *
 * Idempotent — re-runs after partial failure are no-ops.
 */
return new class extends Migration
{
    public function up()
    {
        $table = 'crypta_tech_seat_fitting_doctrine';

        if (! Schema::hasColumn($table, 'is_locked')) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_locked')->default(false)->after('name');
            });
        }
    }

    public function down()
    {
        Schema::table('crypta_tech_seat_fitting_doctrine', function (Blueprint $t) {
            if (Schema::hasColumn('crypta_tech_seat_fitting_doctrine', 'is_locked')) {
                $t->dropColumn('is_locked');
            }
        });
    }
};
