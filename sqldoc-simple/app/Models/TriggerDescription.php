<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class TriggerDescription extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'trigger_description';
    
    protected $fillable = [
        'dbid',
        'triggername',
        'language',
        'description'
    ];

    public function dbDescription()
    {
        return $this->belongsTo(DbDescription::class, 'dbid');
    }

    public function information()
    {
        return $this->hasOne(TriggerInformation::class, 'id_trigger');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $triggerName = $this->triggername;
        $id = $this->id;

        return match($action) {
            'create' => "/api/triggers",
            'update' => "/api/triggers/{$id}",
            'delete' => "/api/triggers/{$id}",
        };
    }

    
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'dbid' => $this->dbid,
            'triggername' => $this->triggername,
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