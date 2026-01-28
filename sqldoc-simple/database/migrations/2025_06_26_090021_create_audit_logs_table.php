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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('db_id')->nullable()->constrained('db_description');
            $table->string('column_name', 255);
            $table->string('change_type', 10);
            $table->string('change_place', 10);
            $table->text('old_data')->nullable();
            $table->text('new_data');
            $table->foreignId('table_id')->nullable()->constrained('table_description');
            $table->foreignId('ps_id')->nullable()->constrained('ps_description');
            $table->foreignId('view_id')->nullable()->constrained('view_description');
            $table->foreignId('fc_id')->nullable()->constrained('function_description');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
