<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingSync extends Model
{
    protected $table = 'pending_sync';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'data',
        'endpoint',
        'method',
        'retry_count',
        'synced_at',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
        'synced_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return is_null($this->synced_at);
    }

    public function markAsSynced(): void
    {
        $this->update(['synced_at' => now()]);
    }

    public function incrementRetry(string $error = null): void
    {
        $this->increment('retry_count');
        $this->update(['error_message' => $error]);
    }
}