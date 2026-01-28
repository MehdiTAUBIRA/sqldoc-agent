<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class ViewColumn extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'view_column';
    
    protected $fillable = [
        'id_view',
        'name',
        'type',
        'is_nullable',
        'description',
        'max_lengh',
        'precision',
        'scale',
    ];

    // Pas de timestamps automatiques pour cette table
    public $timestamps = true;

    public function viewDescription()
    {
        return $this->belongsTo(ViewDescription::class, 'id_view');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $view = $this->viewDescription;
        $viewName = $view ? $view->viewname : 'unknown';
        $columnId  = $this->id;

        return match($action) {
            'create' => "/api/views/{$viewName}/columns",
            'update' => "/api/views/{$viewName}/columns/{$columnId}",
            'delete' => "/api/views/{$viewName}/columns/{$columnId}",
        };
    }

   
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_view' => $this->id_view,  
            'name' => $this->name,
            'type' => $this->type,
            'is_nullable' => $this->is_nullable,
            'max_lengh' => $this->max_lengh,
            'description' => $this->description,
            'precision' => $this->precision,
            'scale' => $this->scale,
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