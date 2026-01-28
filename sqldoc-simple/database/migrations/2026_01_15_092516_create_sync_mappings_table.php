<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');  
            $table->integer('local_id');    
            $table->integer('remote_id');   
            $table->timestamps();
            
            $table->unique(['entity_type', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_mappings');
    }
};