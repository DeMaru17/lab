<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerjalananDinasTable extends Migration
{
    public function up()
    {
        Schema::create('perjalanan_dinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relasi ke tabel users
            $table->date('tanggal_berangkat'); // Tanggal berangkat dinas
            $table->date('perkiraan_tanggal_pulang'); // Perkiraan tanggal pulang
            $table->date('tanggal_pulang')->nullable(); // Tanggal pulang sebenarnya (opsional)
            $table->string('jurusan'); // Tujuan perjalanan dinas
            $table->integer('lama_dinas')->nullable(); // Lama dinas (dihitung otomatis di backend)
            $table->enum('status', ['berlangsung', 'selesai'])->default('berlangsung'); // Status perjalanan dinas
            $table->timestamps(); // Kolom created_at dan updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('perjalanan_dinas');
    }
}