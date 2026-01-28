<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{

    use HasFactory;
    
    protected $dateFormat = 'd-m-Y H:i:s';
   
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'db_id',
        'table_id',
        'view_id',
        'ps_id',
        'column_name',
        'change_type',
        'old_data',
        'new_data',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dbdescription()
    {
        return $this->belongsTo(DbDescription::class);
    }

    public function tableDescription()
    {
        return $this->belongsTo(TableDescription::class, 'id_table', 'id');
    }

    public function psDescription()
    {
        return $this->belongsTo(PsDescription::class, 'id_ps', 'id');
    }

    public function viewDescription()
    {
        return $this->belongsTo(ViewDescription::class, 'id_view', 'id');
    }

    public function funcDescription()
    {
        return $this->belongsTo(FunctionDescription::class, 'id_fc', 'id');
    }
}