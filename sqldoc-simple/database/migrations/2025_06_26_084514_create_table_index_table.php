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
        Schema::create('table_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_table')->nullable()->constrained('table_description');
            $table->string('name', 255)->nullable();
            $table->string('type', 50)->nullable();
            $table->text('column')->nullable();
            $table->string('properties', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_index');
    }
};
