<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    protected $table = 'projects';

    protected $fillable = [
        'name',
        'db_type',
        'user_id',
        'description',
        'release'
    ];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function dbDescription()
    {
        return $this->hasOne(DbDescription::class, 'project_id');
    }

     public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeDeleted($query)
    {
        return $query->onlyTrashed();
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
