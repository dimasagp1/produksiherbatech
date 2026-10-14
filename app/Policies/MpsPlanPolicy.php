<?php

namespace App\Policies;

use App\Models\MpsPlan;
use App\Models\User;

class MpsPlanPolicy
{
    /**
     * Superadmin bypasses policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['ppic', 'manager']);
    }

    public function view(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole(['ppic', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('ppic');
    }

    public function update(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && $mpsPlan->status === 'draft';
    }

    public function delete(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && $mpsPlan->status === 'draft';
    }

    public function approve(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && $mpsPlan->status === 'draft';
    }

    public function activate(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && in_array($mpsPlan->status, ['draft', 'approved']);
    }

    public function generateWeeklyPlans(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && in_array($mpsPlan->status, ['approved', 'active']);
    }

    public function createRevision(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && in_array($mpsPlan->status, ['approved', 'active']);
    }

    public function close(User $user, MpsPlan $mpsPlan): bool
    {
        return $user->hasRole('ppic') && in_array($mpsPlan->status, ['approved', 'active']);
    }
}
