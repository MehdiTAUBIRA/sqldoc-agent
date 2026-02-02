<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class ViewInformation extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'view_information';
    
    protected $fillable = [
        'id_view',
        'schema_name',
        'definition',
        'creation_date',
        'last_change_date'
    ];

    protected $casts = [
    'creation_date' => 'datetime',
    'last_change_date' => 'datetime',
    ];


    public function viewDescription()
    {
        return $this->belongsTo(ViewDescription::class, 'id_view');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $view = $this->viewDescription;
        $viewName = $view ? $view->viewname : 'unknown';
        $informationId  = $this->id;

        return match($action) {
            'create' => "/api/views/{$viewName}/information",
            'update' => "/api/views/{$viewName}/information/{$informationId}",
            'delete' => "/api/views/{$viewName}/information/{$informationId}",
        };
    }

   
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_view' => $this->id_view,  
            'schema_name' => $this->schema_name,
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

        if (is_null($this->id_view)) {  
            return false;
        }

        if (!$this->viewDescription) {
            return false;
        }

        return true;
    }
}