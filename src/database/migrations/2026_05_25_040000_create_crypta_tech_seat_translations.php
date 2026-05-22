<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('crypta_tech_seat_translations')) {
            return;
        }

        Schema::create('crypta_tech_seat_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            /* `source` distinguishes which SDE table the row translates: invTypes,
               invGroups, invCategories, invMarketGroups. Different source-id namespaces
               can overlap numerically, so the unique key MUST include source. */
            $table->string('source', 20);
            $table->unsignedInteger('source_id');
            $table->string('locale', 10);
            $table->string('name', 255);

            $table->unique(['source', 'source_id', 'locale'], 'ctst_unique');
            /* Lookup-side index: "give me all zh-CN names for these N type_ids" hits this. */
            $table->index(['source', 'locale'], 'ctst_source_locale_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('crypta_tech_seat_translations');
    }
};
