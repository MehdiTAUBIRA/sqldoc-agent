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
        'token_encrypted',
        'api_url',
        'connected_at',
        'last_sync_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
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
            'agent_id' => null,
            'tenant_id' => null,
            'tenant_name' => null,
            'connected_at' => null,
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