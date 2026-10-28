<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Core roles are protected from deletion so the ERP can never lose
     * its Admin / Manager / Staff baseline.
     */
    public function isCore(): bool
    {
        return in_array($this->slug, ['admin', 'manager', 'staff'], true);
    }
}
