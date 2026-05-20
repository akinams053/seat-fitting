<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('crypta_tech_seat_fitting_skill_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('tier', 20)->default('minimum');
            $table->timestamps();
        });

        Schema::create('crypta_tech_seat_fitting_skill_plan_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->integer('skill_type_id');
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['plan_id', 'skill_type_id'], 'ctsf_plan_item_unique');
            $table->index('skill_type_id', 'ctsf_plan_item_skill_type_index');

            $table->foreign('plan_id', 'ctsf_plan_item_plan_foreign')
                ->references('id')
                ->on('crypta_tech_seat_fitting_skill_plans')
                ->onDelete('cascade');
        });

        Schema::create('crypta_tech_seat_fitting_skill_plan_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->string('attachable_type', 20);
            $table->unsignedBigInteger('attachable_id');
            $table->timestamps();

            $table->unique(['plan_id', 'attachable_type', 'attachable_id'], 'ctsf_plan_attach_unique');
            $table->index(['attachable_type', 'attachable_id'], 'ctsf_plan_attach_target_index');

            $table->foreign('plan_id', 'ctsf_plan_attach_plan_foreign')
                ->references('id')
                ->on('crypta_tech_seat_fitting_skill_plans')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('crypta_tech_seat_fitting_skill_plan_attachments');
        Schema::dropIfExists('crypta_tech_seat_fitting_skill_plan_items');
        Schema::dropIfExists('crypta_tech_seat_fitting_skill_plans');
    }
};
