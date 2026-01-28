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
        Schema::create('ps_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_ps')->nullable()->constrained('ps_description');
            $table->string('schema', 10)->nullable();
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
        Schema::dropIfExists('ps_information');
    }
};
