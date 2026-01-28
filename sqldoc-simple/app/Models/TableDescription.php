<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class TableDescription  extends Model
{
    use HasFactory, SyncableModel;

    protected $dateFormat = 'd-m-Y H:i:s';
   
   protected $table = 'table_description';

   protected $fillable = [
    'id',
    'dbid',
    'tablename',
    'language',
    'description',
    'created_at',
    'updated_at',
   ];

   protected $hidden =[
    'created_at', 
    'updated_at'
    ];

    public function dbdescription()
    {
        return $this->belongsTo(DbDescription::class, 'dbid', 'id');
    }

    public function indexes()
    {
        return $this->hasMany(TableIndex::class, 'id_table', 'id');
    }

    public function relations()
    {
        return $this->hasMany(TableRelation::class, 'id_table', 'id');
    }

    public function structures()
    {
        return $this->hasMany(TableStructure::class, 'id_table');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $tableName = $this->tablename;
        $id = $this->id;

        return match($action) {
            'create' => "/api/tables",
            'update' => "/api/tables/{$id}",
            'delete' => "/api/tables/{$id}",
        };
    }

    
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'dbid' => $this->dbid,
            'tablename' => $this->tablename,
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