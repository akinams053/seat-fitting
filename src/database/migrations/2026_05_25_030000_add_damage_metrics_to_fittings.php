<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'crypta_tech_seat_fittings';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            if (! Schema::hasColumn($this->table, 'minimum_dps')) {
                $table->decimal('minimum_dps', 12, 2)->nullable()->after('ship_type_id');
            }

            if (! Schema::hasColumn($this->table, 'minimum_dph')) {
                $table->decimal('minimum_dph', 12, 2)->nullable()->after('minimum_dps');
            }

            if (! Schema::hasColumn($this->table, 'advanced_dps')) {
                $table->decimal('advanced_dps', 12, 2)->nullable()->after('minimum_dph');
            }

            if (! Schema::hasColumn($this->table, 'advanced_dph')) {
                $table->decimal('advanced_dph', 12, 2)->nullable()->after('advanced_dps');
            }
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            foreach (['advanced_dph', 'advanced_dps', 'minimum_dph', 'minimum_dps'] as $column) {
                if (Schema::hasColumn($this->table, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
