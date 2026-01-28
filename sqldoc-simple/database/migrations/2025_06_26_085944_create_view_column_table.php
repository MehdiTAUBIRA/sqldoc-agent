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
        Schema::create('view_column', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_view')->nullable()->constrained('view_description');
            $table->string('name', 255)->nullable();
            $table->string('type', 50)->nullable();
            $table->tinyInteger('is_nullable')->nullable();
            $table->integer('max_length')->nullable();
            $table->integer('precision')->nullable();
            $table->integer('scale')->nullable();
            $table->text('description')->nullable();
            $table->string('rangevalues')->nullable();
            $table->foreignId('release_id')->nullable()->constrained('release');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('view_column');
    }
};
