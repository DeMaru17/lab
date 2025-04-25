<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom string 'vendor' yang lama jika ada
            if (Schema::hasColumn('users', 'vendor')) {
                $table->dropColumn('vendor');
            }
            // Tambahkan kolom foreign key 'vendor_id' (nullable)
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('jabatan') // Sesuaikan posisi jika perlu
                ->constrained('vendors') // Nama tabel vendors
                ->onDelete('set null'); // Jika vendor dihapus, user vendor_id jadi null
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus foreign key & kolom vendor_id
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');

            // (Opsional) Kembalikan kolom string 'vendor' jika perlu rollback sempurna
            // $table->string('vendor')->nullable()->after('signature_path');
        });
    }
};
