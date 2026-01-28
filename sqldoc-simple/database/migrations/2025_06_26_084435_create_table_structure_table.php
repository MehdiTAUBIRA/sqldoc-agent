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
        Schema::create('table_structure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_table')->nullable()->constrained('table_description');
            $table->string('column', 100)->nullable();
            $table->string('type', 50)->nullable();
            $table->tinyInteger('nullable')->nullable();
            $table->char('key', 2)->nullable();
            $table->text('description')->nullable();
            $table->text('rangevalues')->nullable();
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
        Schema::dropIfExists('table_structure');
    }
};
