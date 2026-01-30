<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AgentIdentity extends Model
{
    protected $table = 'agent_identity';

    public $timestamps = false;

    protected $fillable = [
        'agent_id',
        'tenant_id',
        'tenant_name',
        'organization_name',
        'token_encrypted',
        'api_url',
        'available_tenants',
        'is_active',
        'connected_at',
        'last_connected_at',
        'last_sync_at',
    ];

    protected $casts = [
        'available_tenants' => 'array',
        'connected_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Obtenir le token décrypté
     */
    public function getTokenAttribute(): ?string
    {
        if (!$this->token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->token_encrypted);
        } catch (\Exception $e) {
            \Log::error('Error decrypting agent token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }

    public static function getHistory()
    {
        return self::where('is_active', false)
            ->orderBy('last_connected_at', 'desc')
            ->orderByDesc('connected_at')
            ->get();
    }

    public function setActive(): void
    {
        // Désactiver tous les autres agents
        self::where('id', '!=', $this->id)->update(['is_active' => false]);
        
        // Activer celui-ci
        $this->update([
            'is_active' => true,
            'last_connected_at' => now(),
        ]);
    }

    /**
     * Définir le token (sera crypté automatiquement)
     */
    public function setTokenAttribute(string $token): void
    {
        $this->attributes['token_encrypted'] = Crypt::encryptString($token);
    }

    /**
     * Vérifier si l'agent est connecté
     */
    public function isConnected(): bool
    {
        return !is_null($this->agent_id) && 
               !is_null($this->tenant_id) && 
               !is_null($this->connected_at);
    }

    /**
     * Déconnecter l'agent (garde le token pour reconnecter)
     */
    public function disconnect(): void
    {
        $this->update([
            'is_active' => false,
        ]);
    }

    /**
     * Supprimer complètement l'identité
     */
    public function purge(): void
    {
        $this->delete();
    }
}