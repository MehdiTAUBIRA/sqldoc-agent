<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProjectAccess extends Model
{
    use HasFactory;

    protected $table = 'user_project_accesses';

    protected $fillable = [
        'user_id',
        'project_id',
        'access_level'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Niveaux d'accès disponibles
     */
    const ACCESS_LEVELS = [
        'read' => 'Lecture seule',
        'write' => 'Lecture/Écriture',
        'Admin' => 'Administration complète'
    ];

    /**
     * Relation avec User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Vérifier si l'utilisateur a un niveau d'accès minimum
     */
    public function hasMinimumAccess($requiredLevel)
    {
        $levels = ['read', 'write', 'Admin'];
        $currentLevelIndex = array_search($this->access_level, $levels);
        $requiredLevelIndex = array_search($requiredLevel, $levels);
        
        return $currentLevelIndex !== false && $currentLevelIndex >= $requiredLevelIndex;
    }

    /**
     * Scope pour filtrer par niveau d'accès
     */
    public function scopeWithAccessLevel($query, $level)
    {
        return $query->where('access_level', $level);
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par projet
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}