<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Traits\SyncableModel;

class TableRelation  extends Model
{
    use HasFactory, SyncableModel;

    protected $dateFormat = 'd-m-Y H:i:s';
   
   protected $table = 'table_relations';

   protected $fillable = [
    'id',
    'id_table',
    'constraints',
    'column',
    'referenced_table',
    'referenced_column',
    'action'
   ];

    public function tabledescription()
    {
        return $this->belongsTo(TableDescription::class, 'id_table', 'id');
    }

    protected function getSyncEndpoint(string $action): string
    {
        $table = $this->tableDescription;
        $tableName = $table ? $table->tablename : 'unknown';
        $relationId = $this->id;

        return match($action) {
            'create' => "/api/tables/{$tableName}/relations",
            'update' => "/api/tables/{$tableName}/relations/{$relationId}",
            'delete' => "/api/tables/{$tableName}/relations/{$relationId}",
        };
    }

   
    protected function getSyncData(): array
    {
        return [
            'id' => $this->id,
            'id_table' => $this->id_table,  
            'constraints' => $this->constraints,
            'column' => $this->column,
            'referenced_table' => $this->referenced_table,
            'referenced_column' => $this->referenced_column,
            'action' => $this->action,
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