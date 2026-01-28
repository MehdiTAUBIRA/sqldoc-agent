<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class TableIndex extends Model
{
    use HasFactory, SyncableModel;

    protected $dateFormat = 'd-m-Y H:i:s';
   
    protected $table = 'table_index';

    protected $fillable = [
        'id',
        'id_table',
        'name',
        'type',
        'column',
        'properties'
    ];

    public function tabledescription()
    {
        return $this->belongsTo(TableDescription::class, 'id_table', 'id');
    }

    
    protected function getSyncEndpoint(string $action): string
    {
        $table = $this->tableDescription;
        $tableName = $table ? $table->tablename : 'unknown';
        $indexId = $this->id;

        return match($action) {
            'create' => "/api/tables/{$tableName}/indexes",
            'update' => "/api/tables/{$tableName}/indexes/{$indexId}",
            'delete' => "/api/tables/{$tableName}/indexes/{$indexId}",
        };
    }

   
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_table' => $this->id_table,  
            'name' => $this->name,
            'type' => $this->type,
            'column' => $this->column,
            'properties' => $this->properties,
        ];
    }

    public function shouldSync(): bool
    {
        if (!agentConnected()) {
            return false;
        }

        if (is_null($this->id_table)) {  
            return false;
        }

        if (!$this->tableDescription) {
            return false;
        }

        return true;
    }
}