<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class FuncInformation extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'func_information';
    
    protected $fillable = [
        'id_func',
        'type',
        'return_type',
        'definition',
        'creation_date',
        'last_change_date'
    ];

    // Rename field for ORM matching if needed
    protected $casts = [
        'creation_date' => 'datetime',
        'last_change_date' => 'datetime'
    ];

    // S'assurer que Laravel reconnaît le champ id_func
    public function getIdFuncAttribute()
    {
        return $this->attributes['id_func'];
    }

    public function functionDescription()
    {
        return $this->belongsTo(FunctionDescription::class, 'id_func');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $function = $this->functionDescription;
        $functionName = $function ? $function->functionname : 'unknown';
        $informationId = $this->id;

        return match($action) {
            'create' => "/api/functions/{$functionName}/information",
            'update' => "/api/functions/{$functionName}/information/{$informationId}",
            'delete' => "/api/functions/{$functionName}/information/{$informationId}",
        };
    }

    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_func' => $this->id_func,
            'type' => $this->type,
            'return_type' => $this->return_type,
            'definition' => $this->definition,
            'creation_date' => $this->creation_date?->toIso8601String(),
            'last_change_date' => $this->last_change_date?->toIso8601String(),
        ];
    }

    public function shouldSync(): bool
    {
        if (!agentConnected()) {
            return false;
        }

        if (is_null($this->id_func)) {
            return false;
        }

        if (!$this->functionDescription) {
            return false;
        }

        return true;
    }
}