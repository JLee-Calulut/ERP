<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'is_self' => $user->is(auth()->user()),
            ]);

        $roles = Role::orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
        ]);

        return Inertia::render('Erp/Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Erp/Users/Form', [
            'user' => null,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        $user->roles()->sync($request->input('roles', []));

        return redirect()
            ->route('erp.users.index')
            ->with('success', "User \"{$user->name}\" created.");
    }

    public function edit(User $user): Response
    {
        Gate::authorize('view', $user);

        $user->load('roles');

        return Inertia::render('Erp/Users/Form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => $user->roles->pluck('id')->all(),
                'is_self' => $user->is(auth()->user()),
            ],
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(AdminUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->update([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
        ]);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->input('password')),
            ]);
        }

        $user->roles()->sync($request->input('roles', []));

        return redirect()
            ->route('erp.users.index')
            ->with('success', "User \"{$user->name}\" updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('erp.users.index')
            ->with('success', "User \"{$user->name}\" deleted.");
    }

    private function roleOptions(): array
    {
        return Role::orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
        ])->values()->all();
    }
}
