<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class DbDescription extends Model
{
    use HasFactory, SyncableModel; 

    protected $dateFormat = 'd-m-Y H:i:s';
   
    protected $table = 'db_description';

    protected $fillable = [
        'user_id',
        'language',
        'dbname',
        'project_id',
        'description', 
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tableDescriptions()
    {
        return $this->hasMany(TableDescription::class, 'dbid', 'id');
    }

    public function viewDescriptions()
    {
        return $this->hasMany(ViewDescription::class, 'dbid', 'id');
    }

    public function psDescriptions()
    {
        return $this->hasMany(PsDescription::class, 'dbid', 'id');
    }

    public function triggerDescriptions()
    {
        return $this->hasMany(TriggerDescription::class, 'dbid', 'id');
    }

    public function functionDescriptions()
    {
        return $this->hasMany(FunctionDescription::class, 'dbid', 'id');
    }

    public function releases()
    {
        return $this->hasMany(Release::class, 'id_db', 'id');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $id = $this->id;

        return match($action) {
            'create' => "/api/projects",
            'update' => "/api/projects/{$id}",
            'delete' => "/api/projects/{$id}",
        };
    }

    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'language' => $this->language,
            'dbname' => $this->dbname,
            'project_id' => $this->project_id,
            'description' => $this->description,
        ];
    }

    public function shouldSync(): bool
    {
        if (!agentConnected()) {
            return false;
        }

        if (is_null($this->user_id) || is_null($this->project_id)) {
            return false;
        }

        return true;
    }
}