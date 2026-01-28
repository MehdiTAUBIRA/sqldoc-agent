<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\User;
use App\Models\Role;
use App\Models\UserProjectAccess;
use App\Observers\UserObserver;
use App\Observers\RoleObserver;
use App\Observers\UserProjectAccessObserver;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ... événements existants ...
    ];

    public function boot(): void
    {
        // ✅ Enregistrer les observers
        User::observe(UserObserver::class);
        Role::observe(RoleObserver::class);
        UserProjectAccess::observe(UserProjectAccessObserver::class);
    }
}