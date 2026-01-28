<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class PsInformation extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'ps_information';
    
    protected $fillable = [
        'id_ps',
        'schema',
        'creation_date',
        'last_change_date',
        'definition'
    ];

    // Convertir les dates en instances Carbon
    protected $casts = [
        'creation_date' => 'datetime',
        'last_change_date' => 'datetime'
    ];

    // Pas de timestamps pour cette table
    public $timestamps = true;

    public function psDescription()
    {
        return $this->belongsTo(PsDescription::class, 'id_ps');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $ps = $this->psDescription;
        $psName = $ps ? $ps->psname : 'unknown';
        $informationId = $this->id;

        return match($action) {
            'create' => "/api/procedures/{$psName}/information",
            'update' => "/api/procedures/{$psName}/information/{$informationId}",
            'delete' => "/api/procedures/{$psName}/information/{$informationId}",
        };
    }

    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_ps' => $this->id_ps,
            'schema' => $this->schema,
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

        if (is_null($this->id_ps) || !$this->psDescription) {
            return false;
        }

        return true;
    }
    
}