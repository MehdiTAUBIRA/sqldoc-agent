<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class FunctionDescription extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'function_description';
    
    protected $fillable = [
        'dbid',
        'functionname',
        'language',
        'description'
    ];

    public function dbDescription()
    {
        return $this->belongsTo(DbDescription::class, 'dbid');
    }

    public function information()
    {
        return $this->hasOne(FuncInformation::class, 'id_func');
    }

    public function parameters()
    {
        return $this->hasMany(FuncParameter::class, 'id_func');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $functionName = $this->functionname;
        $id = $this->id;

        return match($action) {
            'create' => "/api/functions",
            'update' => "/api/functions/{$id}",
            'delete' => "/api/functions/{$id}",
        };
    }

    
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'dbid' => $this->dbid,
            'functionname' => $this->functionname,
            'language' => $this->language,
            'description' => $this->description,
        ];
    }

    
    public function shouldSync(): bool
    {
        
        if (!agentConnected()) {
            return false;
        }

        
        if (is_null($this->dbid)) {
            return false;
        }

        return true;
    }
}