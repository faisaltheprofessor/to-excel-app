<?php

namespace App\Policies;

use App\Models\OrganizationStructure;
use App\Models\User;

class OrganizationStructurePolicy
{
    public function update(User $user, OrganizationStructure $tree): bool
    {
        return $tree->canBeEditedBy($user->id);
    }

    public function finalize(User $user, OrganizationStructure $tree): bool
    {
        // Only editor (lock holder) can finalize
        return $tree->isLocked() && (int)$tree->locked_by === (int)$user->id;
    }

    public function version(User $user, OrganizationStructure $tree): bool
    {
        // Anyone can branch a finalized version (adjust as needed)
        return $tree->status === 'abgeschlossen';
    }
}
