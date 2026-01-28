<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class TriggerInformation extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'trigger_information';
    
    protected $fillable = [
        'id_trigger',
        'table',
        'schema',
        'type',
        'event',
        'state',
        'creation_date',
        'definition',
        'last_change_date',
        'is_disabled'
    ];

    // Convertir les dates en instances Carbon
    protected $casts = [
        'creation_date' => 'datetime',
        'last_change_date' => 'datetime',
        'is_disabled' => 'boolean'
    ];

    // Pas de timestamps pour cette table
    public $timestamps = true;

    public function triggerDescription()
    {
        return $this->belongsTo(TriggerDescription::class, 'id_trigger');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $trigger = $this->triggerDescription;
        $triggerName = $trigger ? $trigger->triggername : 'unknown';
        $informationId = $this->id;

        return match($action) {
            'create' => "/api/triggers/{$triggerName}/information",
            'update' => "/api/triggers/{$triggerName}/information/{$informationId}",
            'delete' => "/api/triggers/{$triggerName}/information/{$informationId}",
        };
    }

    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_trigger' => $this->id_trigger,
            'schema' => $this->schema,
            'table' => $this->table,
            'type' => $this->type,
            'event' => $this->event,
            'state' => $this->state,
            'definition' => $this->definition,
            'is_disabled' => $this->is_disabled,
            'creation_date' => $this->creation_date?->toIso8601String(),
            'last_change_date' => $this->last_change_date?->toIso8601String(),
        ];
    }

    public function shouldSync(): bool
    {
        if (!agentConnected()) {
            return false;
        }

        if (is_null($this->id_trigger)) {
            return false;
        }

        if (!$this->triggerDescription) {
            return false;
        }

        return true;
    }
}