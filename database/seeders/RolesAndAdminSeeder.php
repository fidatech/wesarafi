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

        // Create Admin User
        $create_super_admin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('admin@123'),
                'branch_id' => 1,
                'status' => true,
            ]
        );

        $create_admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => Hash::make('admin@123'),
                'branch_id' => 1,
                'status' => true,
            ]
        );

        // Create Default Role User
        // DB::table('role_user')->insert([
        //     [
        //         'role_id' => $superAdmin->id,
        //         'user_id' => $create_super_admin->id
        //     ],
        //     [
        //         'role_id' => $admin->id,
        //         'user_id' => $create_admin->id
        //     ]
        // ]);

        // Assign Role
        $create_super_admin->assignRole('Super Admin');
        $create_admin->assignRole('Admin');
    }
}
