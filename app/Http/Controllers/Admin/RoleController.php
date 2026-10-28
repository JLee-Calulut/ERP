<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'is_core' => $role->isCore(),
            ]);

        return Inertia::render('Erp/Roles/Index', [
            'roles' => $roles,
            'permissionCount' => Permission::count(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('Erp/Roles/Form', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);

        $role = Role::create([
            'name' => $request->input('name'),
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug($request->input('name')),
            'description' => $request->input('description'),
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        return redirect()
            ->route('erp.roles.index')
            ->with('success', "Role \"{$role->name}\" created.");
    }

    public function edit(Role $role): Response
    {
        Gate::authorize('view', $role);

        $role->load('permissions');

        return Inertia::render('Erp/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_core' => $role->isCore(),
                'permission_ids' => $role->permissions->pluck('id')->all(),
            ],
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $role->update([
            'name' => $request->input('name'),
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug($request->input('name')),
            'description' => $request->input('description'),
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        return redirect()
            ->route('erp.roles.index')
            ->with('success', "Role \"{$role->name}\" updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $role->delete();

        return redirect()
            ->route('erp.roles.index')
            ->with('success', "Role \"{$role->name}\" deleted.");
    }

    private function permissionGroups(): array
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->slug, '.'))
            ->map(function ($permissions, string $module) {
                return [
                    'module' => $this->moduleLabel($module),
                    'key' => $module,
                    'permissions' => $permissions->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                    ])->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'dashboard' => 'Dashboard',
            'inventory' => 'Inventory',
            'sales' => 'Sales',
            'purchasing' => 'Purchasing',
            'accounting' => 'Accounting',
            'reports' => 'Reports',
            'users' => 'User management',
            'roles' => 'Roles & permissions',
            default => Str::headline($module),
        };
    }
}
