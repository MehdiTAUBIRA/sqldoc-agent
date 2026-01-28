<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 10);
            $table->text('description')->nullable();
        });

        // Insertion des données initiales
        DB::table('permissions')->insert([
            [
                'name' => 'create',
                'description' => 'créer des descriptions'
            ],
            [
                'name' => 'read',
                'description' => 'permet uniquement de lire'
            ],
            [
                'name' => 'update',
                'description' => 'permet de modifier des informations'
            ],
            [
                'name' => 'delete',
                'description' => 'permet de supprimer des informations'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
