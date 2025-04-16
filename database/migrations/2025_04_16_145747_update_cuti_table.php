<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCutiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cuti', function (Blueprint $table) {
            // Ganti kolom 'alasan' menjadi 'keperluan'
            $table->renameColumn('alasan', 'keperluan');

            // Tambahkan kolom 'alamat_selama_cuti'
            $table->string('alamat_selama_cuti')->nullable()->after('keperluan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cuti', function (Blueprint $table) {
            // Kembalikan perubahan
            $table->renameColumn('keperluan', 'alasan');
            $table->dropColumn('alamat_selama_cuti');
        });
    }
}