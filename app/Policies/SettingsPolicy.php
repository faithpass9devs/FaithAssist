<?php

namespace App\Policies;

use App\Models\User;

class SettingsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ajustes.read');
    }

    public function update(User $user): bool
    {
        return $user->can('ajustes.update');
    }
}
