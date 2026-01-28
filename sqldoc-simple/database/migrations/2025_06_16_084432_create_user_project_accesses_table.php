<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_project_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->enum('access_level', ['read', 'write', 'admin'])->default('read');
            $table->timestamps();
            $table->softDeletes();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'project_id']);
            $table->index('access_level');
            
            // Contrainte d'unicité pour éviter les doublons
            $table->unique(['user_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_project_accesses');
    }
};
