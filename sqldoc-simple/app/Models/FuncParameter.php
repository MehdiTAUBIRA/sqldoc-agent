<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class FuncParameter extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'func_parameter';
    
    protected $fillable = [
        'id_func',
        'name',
        'type',
        'output',
        'description',
    ];

    public $timestamps = false;

    public function functionDescription()
    {
        return $this->belongsTo(FunctionDescription::class, 'id_func');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $function = $this->functionDescription;
        $functionName = $function ? $function->functionname : 'unknown';
        $parameterId = $this->id;

        return match($action) {
            'create' => "/api/functions/{$functionName}/parameters",
            'update' => "/api/functions/{$functionName}/parameters/{$parameterId}",
            'delete' => "/api/functions/{$functionName}/parameters/{$parameterId}",
        };
    }

    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_func' => $this->id_func,
            'name' => $this->name,
            'type' => $this->type,
            'output' => $this->output,
            'description' => $this->description,
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