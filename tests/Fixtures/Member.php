<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures;

use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;

/** A user of one team, for the tenant-aware panel. */
class Member extends User implements HasTenants
{
    protected $table = 'users';

    protected $guarded = [];

    public function canAccessTenant(Model $tenant): bool
    {
        return (int) $this->getAttribute('team_id') === (int) $tenant->getKey();
    }

    public function getTenants(Panel $panel): Collection
    {
        return Team::query()->whereKey($this->getAttribute('team_id'))->get();
    }
}
