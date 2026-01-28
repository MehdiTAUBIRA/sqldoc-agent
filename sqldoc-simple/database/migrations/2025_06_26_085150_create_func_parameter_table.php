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
        Schema::create('func_parameter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_func')->nullable()->constrained('function_description');
            $table->string('name', 255)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('output', 10)->nullable();
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
        Schema::dropIfExists('func_parameter');
    }
};
