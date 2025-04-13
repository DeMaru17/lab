<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateJabatanInUsersTable extends Migration
{
    public function up()
    {
        // Modify the jabatan column to string *before* updating values
        Schema::table('users', function (Blueprint $table) {
            $table->string('jabatan', 50)->nullable()->change(); // Adjust length (50) as needed
        });

        // Update existing 'asisten manager' to 'asisten manager analis'
        DB::table('users')
            ->where('jabatan', 'asisten manager')
            ->update(['jabatan' => 'asisten manager analis']);

        // Modify the jabatan enum to include new positions
        Schema::table('users', function (Blueprint $table) {
            $table->enum('jabatan', ['manager', 'asisten manager analis', 'asisten manager preparator', 'preparator', 'analis', 'mekanik', 'admin'])->nullable()->change();
        });
    }

    public function down()
    {
        // Rollback changes
        Schema::table('users', function (Blueprint $table) {
            $table->enum('jabatan', ['manager', 'asisten manager', 'preparator', 'analis', 'mekanik', 'admin'])->nullable()->change();
        });

        // Revert 'asisten manager analis' back to 'asisten manager'
        DB::table('users')
            ->where('jabatan', 'asisten manager analis')
            ->update(['jabatan' => 'asisten manager']);

         // Change the column back to enum
         Schema::table('users', function (Blueprint $table) {
            $table->enum('jabatan', ['manager', 'asisten manager', 'preparator', 'analis', 'mekanik', 'admin'])->nullable()->change();
        });
    }
}