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
        Schema::create('view_description', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dbid')->nullable()->constrained('db_description');
            $table->string('viewname', 255)->nullable();
            $table->text('description')->nullable();
            $table->char('language', 3)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('view_description');
    }
};
