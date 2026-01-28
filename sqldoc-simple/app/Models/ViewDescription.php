<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class ViewDescription extends Model
{
    use HasFactory, SyncableModel;

    protected $table = 'view_description';
    
    protected $fillable = [
        'dbid',
        'viewname',
        'language',
        'description'
    ];

    public function dbDescription()
    {
        return $this->belongsTo(DbDescription::class, 'dbid');
    }

    public function information()
    {
        return $this->hasOne(ViewInformation::class, 'id_view');
    }

    public function columns()
    {
        return $this->hasMany(ViewColumn::class, 'id_view');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $viewName = $this->viewname;
        $id = $this->id;

        return match($action) {
            'create' => "/api/views",
            'update' => "/api/views/{$id}",
            'delete' => "/api/views/{$id}",
        };
    }

    
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'dbid' => $this->dbid,
            'viewname' => $this->viewname,
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