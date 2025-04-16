<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsProcessedToPerjalananDinasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('perjalanan_dinas', function (Blueprint $table) {
            $table->boolean('is_processed')->default(false)->after('status'); // Menandai apakah perjalanan dinas sudah diproses
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('perjalanan_dinas', function (Blueprint $table) {
            $table->dropColumn('is_processed');
        });
    }
}
