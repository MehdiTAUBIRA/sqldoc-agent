<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $dateFormat = "Y-d-m H:i:s";
    
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function auditLog()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permissions()
    {
        return $this->role->permissions->pluck('name');
    }

    public function isAdmin()
    {
        if ($this->role && strtolower($this->role->name) === 'admin') {
            return true;
        }

        if (isset($this->role_name) && strtolower($this->role_name) === 'admin') {
            return true;
        }

        return false;
    }

    public function hasPermission($permission)
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()
            ->where('name', $permission)
            ->exists();
    }


    /**
     * Relation avec les accès aux projets accordés
     */
    public function projectAccesses()
    {
        return $this->hasMany(UserProjectAccess::class);
    }

    /**
     * Relation avec les projets accessibles (via les accès accordés)
     */
    public function accessibleProjects()
    {
        return $this->belongsToMany(Project::class, 'user_project_accesses')
                    ->withPivot('access_level')
                    ->withTimestamps();
    }

    public function canAccessProject($projectId, $minimumLevel = 'read')
    {
        // Le propriétaire du projet a tous les droits
        if ($this->projects()->where('id', $projectId)->exists()) {
            return true;
        }

        // Les admins ont accès à tous les projets
        if ($this->isAdmin()) {
            return true;
        }

        // Vérifier les accès accordés
        $access = $this->projectAccesses()->where('project_id', $projectId)->first();
        
        if (!$access) {
            return false;
        }

        return $access->hasMinimumAccess($minimumLevel);
    }

    /**
     * Obtenir le niveau d'accès pour un projet
     */
    public function getProjectAccessLevel($projectId)
    {
        // Le propriétaire du projet a les droits admin
        if ($this->projects()->where('id', $projectId)->exists()) {
            return 'Admin';
        }

        // Les admins ont accès admin à tous les projets
        if ($this->isAdmin()) {
            return 'Admin';
        }

        // Vérifier les accès accordés
        $access = $this->projectAccesses()->where('project_id', $projectId)->first();
        
        return $access ? $access->access_level : null;
    }

    /**
     * Obtenir tous les projets accessibles par l'utilisateur
     */
    public function getAllAccessibleProjects()
    {
        // Si admin, retourner tous les projets
        if ($this->isAdmin()) {
            return Project::whereNull('deleted_at')->get();
        }

        // Sinon, retourner les projets possédés + les projets avec accès accordé
        $ownedProjects = $this->projects()->whereNull('deleted_at')->get();
        $accessibleProjects = $this->accessibleProjects()->whereNull('deleted_at')->get();

        // Fusionner et supprimer les doublons
        return $ownedProjects->merge($accessibleProjects)->unique('id');
    }

    public function belongsToAgentTenant(): bool
    {
        $identity = app(\App\Services\AgentAuthService::class)->getIdentity();
        
        if (!$identity) {
            return false;
        }

        return $this->tenant_id === $identity->tenant_id;
    }
    
}
