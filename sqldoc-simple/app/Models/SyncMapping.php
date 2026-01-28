<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncMapping extends Model
{
    protected $fillable = [
        'entity_type',
        'local_id',
        'remote_id',
    ];

    /**
     * Obtenir l'ID distant pour un ID local
     */
    public static function getRemoteId(string $entityType, int $localId): ?int
    {
        $mapping = self::where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->first();
        
        return $mapping?->remote_id;
    }

    /**
     * Sauvegarder un mapping
     */
    public static function saveMapping(string $entityType, int $localId, int $remoteId): void
    {
        self::updateOrCreate(
            [
                'entity_type' => $entityType,
                'local_id' => $localId,
            ],
            [
                'remote_id' => $remoteId,
            ]
        );
    }
}