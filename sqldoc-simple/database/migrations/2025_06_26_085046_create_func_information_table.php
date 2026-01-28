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
        Schema::create('func_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_func')->nullable()->constrained('function_description');
            $table->string('type', 255)->nullable();
            $table->text('return_type')->nullable();
            $table->timestamp('creation_date')->nullable();
            $table->timestamp('last_change_date')->nullable();
            $table->text('definition')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('func_information');
    }
};
