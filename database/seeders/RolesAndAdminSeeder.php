<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Default Branch
        $branch = Branch::firstOrCreate([
            'name' => 'Main Branch',
            'is_main' => true,
            'province_id' => 1,
            'phone' => 0,
            'address' => null,
            'type' => 'independent',
        ]);

        // Create Roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $branch = Role::firstOrCreate(['name' => 'Branch']);
        $user = Role::firstOrCreate(['name' => 'User']);
        $compliance = Role::firstOrCreate(['name' => 'Compliance']);

        // Create Default Permissions (Optional)
        $permissions = [];
        $models = ['users', 'branches', 'transactions', 'exchange', 'remittance', 'auction', 'journal'];
        foreach ($models as $model) {
            $permissionModels = [
                "view $model",
                "create $model",
                "edit $model",
                "delete $model",
            ];
            foreach ($permissionModels as $perm) {
                $permissions[] = Permission::firstOrCreate(['name' => $perm]);
            }
        }

        $superAdmin->syncPermissions($permissions);
        $admin->syncPermissions($permissions);

        // Never create publicly documented default accounts with known passwords.
        // Existing accounts are preserved; new bootstrap accounts require explicit
        // credentials supplied privately through the deployment environment.
        $bootstrap = [
            ['role' => 'Super Admin', 'email' => env('WESARAFI_SUPERADMIN_EMAIL'), 'name' => 'Super Admin', 'password' => env('WESARAFI_SUPERADMIN_PASSWORD')],
            ['role' => 'Admin', 'email' => env('WESARAFI_ADMIN_EMAIL'), 'name' => 'Admin', 'password' => env('WESARAFI_ADMIN_PASSWORD')],
        ];

        foreach ($bootstrap as $account) {
            if (blank($account['email']) || blank($account['password'])) {
                continue;
            }
            if (strlen($account['password']) < 16) {
                throw new \\RuntimeException('Bootstrap administrator passwords must contain at least 16 characters.');
            }
            $existing = User::where('email', $account['email'])->first();
            if ($existing) {
                // Never reset a real user's password or silently elevate an existing account.
                continue;
            }
            $created = User::create([
                'name' => $account['name'],
                'email' => $account['email'],
                'username' => 'bootstrap_'.\\Illuminate\\Support\\Str::lower(\\Illuminate\\Support\\Str::random(12)),
                'password' => Hash::make($account['password']),
                'branch_id' => 1,
                'status' => true,
            ]);
            $created->assignRole($account['role']);
        }
    }
}
