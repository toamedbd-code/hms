<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('view employees');
    }

    public function view(User $user, Employee $employee)
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('view employees');
    }

    public function create(User $user)
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('create employees');
    }

    public function update(User $user, Employee $employee)
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('edit employees');
    }

    public function delete(User $user, Employee $employee)
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('delete employees');
    }
}
