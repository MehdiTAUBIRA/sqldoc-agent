<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class TableStructure  extends Model
{
    use HasFactory, SyncableModel;

    protected $dateFormat = 'd-m-Y H:i:s';
   
   protected $table = 'table_structure';

   protected $fillable = [
    'id',
    'id_table',
    'column',
    'type',
    'nullable',
    'key',
    'description',
    'rangevalues',
    'release_id'
   ];

   public function tableDescription()
    {
        return $this->belongsTo(TableDescription::class, 'id_table', 'id');
    }

    public function releases()
    {
        return $this->belongsTo(Release::class, 'release_id', 'id');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $table = $this->tableDescription;
        $tableName = $table ? $table->tablename : 'unknown';
        $structureId = $this->id;

        return match($action) {
            'create' => "/api/tables/{$tableName}/columns",
            'update' => "/api/tables/{$tableName}/columns/{$structureId}",
            'delete' => "/api/tables/{$tableName}/columns/{$structureId}",
        };
    }

   
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_table' => $this->id_table,  
            'column' => $this->column,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'key' => $this->key,
            'description' => $this->description,
            'rangevalues' => $this->rangevalues,
            'release_id' => $this->release_id,
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