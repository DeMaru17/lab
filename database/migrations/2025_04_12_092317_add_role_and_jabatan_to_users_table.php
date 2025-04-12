<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleAndJabatanToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manajemen', 'personil'])->default('personil')->after('password');
            $table->enum('jabatan', ['manager', 'asisten manager', 'preparator', 'analis', 'mekanik', 'admin'])->nullable()->after('role');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'jabatan']);
        });
    }
}