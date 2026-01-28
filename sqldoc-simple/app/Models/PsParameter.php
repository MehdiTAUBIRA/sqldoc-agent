<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class PsParameter extends Model
{
    use HasFactory, SyncableModel; 

    protected $table = 'ps_parameter';
    
    protected $fillable = [
        'id_ps',
        'name',
        'type',
        'output',
        'default_value',
        'description',
        'release_id'
    ];

    public $timestamps = true;

    public function psDescription()
    {
        return $this->belongsTo(PsDescription::class, 'id_ps');
    }

    public function release()
    {
        return $this->belongsTo(Release::class, 'release_id');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $ps = $this->psDescription;
        $psName = $ps ? $ps->psname : 'unknown';
        $parameterId = $this->id;

        return match($action) {
            'create' => "/api/procedures/{$psName}/parameters",
            'update' => "/api/procedures/{$psName}/parameters/{$parameterId}",
            'delete' => "/api/procedures/{$psName}/parameters/{$parameterId}",
        };
    }

    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_ps' => $this->id_ps,
            'name' => $this->name,
            'type' => $this->type,
            'output' => $this->output,
            'default_value' => $this->default_value,
            'description' => $this->description,
            'release_id' => $this->release_id,
        ];
    }

    public function shouldSync(): bool
    {
        if (!agentConnected()) {
            return false;
        }

        if (is_null($this->id_ps)) {
            return false;
        }

        if (!$this->psDescription) {
            return false;
        }

        return true;
    }
}