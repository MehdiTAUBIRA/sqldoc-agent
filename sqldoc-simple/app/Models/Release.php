<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Controllers\Traits\SyncableModel;

class Release extends Model
{
    use HasFactory, SyncableModel;

    protected $dateFormat = 'd-m-Y H:i:s';
   
    protected $table = 'release';

    protected $fillable = [
        'project_id',
        'version_number',
        'description',
        'created_at', 
        'updated_at'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function tableStructures()
    {
        return $this->hasMany(TableStructure::class, 'release_id', 'id');
    }

    public function viewColumn()
    {
        return $this->hasMany(ViewColumn::class, 'release_id', 'id'); 
    }

    public function psParameter()
    {
        return $this->hasMany(PsParameter::class, 'release_id', 'id'); 
    }

    public function funcParameter()
    {
        return $this->hasMany(FuncParameter::class, 'release_id', 'id'); 
    }


    protected function getSyncEndpoint(string $action): string
    {
        $id = $this->id;

        return match($action) {
            'create' => "/api/releases",
            'update' => "/api/releases/{$id}",
            'delete' => "/api/releases/{$id}",
        };
    }


    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'version_number' => $this->version_number,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function shouldSync(): bool
    {
        if (!agentConnected()) {
            return false;
        }

        if (is_null($this->project_id)) {
            return false;
        }

        return true;
    }
}