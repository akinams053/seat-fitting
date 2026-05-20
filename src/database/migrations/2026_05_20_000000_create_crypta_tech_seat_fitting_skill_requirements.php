<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crypta_tech_seat_fitting_skill_requirements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('fitting_id')->unsigned();
            $table->integer('skill_type_id');
            $table->string('tier', 20);
            $table->unsignedTinyInteger('level');
            $table->string('source', 20);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['fitting_id', 'skill_type_id', 'tier'], 'ctsf_req_fit_skill_tier_unique');
            $table->index(['fitting_id', 'tier'], 'ctsf_req_fit_tier_index');
            $table->index('skill_type_id', 'ctsf_req_skill_type_index');

            $table->foreign('fitting_id', 'ctsf_req_fit_foreign')
                ->references('fitting_id')
                ->on('crypta_tech_seat_fittings')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crypta_tech_seat_fitting_skill_requirements');
    }
};
