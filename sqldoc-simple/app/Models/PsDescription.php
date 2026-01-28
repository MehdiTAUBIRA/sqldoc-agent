<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class PsDescription extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'ps_description';
    
    protected $fillable = [
        'dbid',
        'psname',
        'language',
        'description'
    ];

    public function dbDescription()
    {
        return $this->belongsTo(DbDescription::class, 'dbid');
    }

    public function information()
    {
        return $this->hasOne(PsInformation::class, 'id_ps');
    }

    public function parameters()
    {
        return $this->hasMany(PsParameter::class, 'id_ps');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $psName = $this->psname;
        $id = $this->id;

        return match($action) {
            'create' => "/api/procedures",
            'update' => "/api/procedures/{$id}",
            'delete' => "/api/procedures/{$id}",
        };
    }

    
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'dbid' => $this->dbid,
            'psname' => $this->psname,
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