<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->primary(['role_id', 'permission_id']);
        });

        DB::table('role_permission')->insert([
            [
                'role_id' => '1',
                'permission_id' => '1'
            ],

            [
                'role_id' => '1',
                'permission_id' => '2'
            ],

            [
                'role_id' => '1',
                'permission_id' => '3'
            ],

            [
                'role_id' => '1',
                'permission_id' => '4'
            ],

        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
