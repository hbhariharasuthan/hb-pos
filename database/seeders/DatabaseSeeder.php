<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Roles (use firstOrCreate to avoid duplicates)
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full system access'
            ]
        );

        $cashierRole = Role::firstOrCreate(
            ['slug' => 'cashier'],
            [
                'name' => 'Cashier',
                'description' => 'POS and sales access'
            ]
        );

        // Create Permissions (use firstOrCreate to avoid duplicates)
        $permissions = [
            ['name' => 'Manage Products', 'slug' => 'manage-products'],
            ['name' => 'Manage Customers', 'slug' => 'manage-customers'],
            ['name' => 'Process Sales', 'slug' => 'process-sales'],
            ['name' => 'View Reports', 'slug' => 'view-reports'],
            ['name' => 'Manage Users', 'slug' => 'manage-users'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // Assign all permissions to admin (sync to avoid duplicates)
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        // Assign specific permissions to cashier (sync to avoid duplicates)
        $cashierRole->permissions()->sync(
            Permission::whereIn('slug', ['process-sales', 'manage-customers'])->pluck('id')
        );

        // Create Admin User (use firstOrCreate to avoid duplicates)
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'phone' => '1234567890'
            ]
        );
        if (!$admin->roles()->where('roles.id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id);
        }

        // Create Cashier User (use firstOrCreate to avoid duplicates)
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@pos.com'],
            [
                'name' => 'Cashier User',
                'password' => Hash::make('password'),
                'phone' => '0987654321'
            ]
        );
        if (!$cashier->roles()->where('roles.id', $cashierRole->id)->exists()) {
            $cashier->roles()->attach($cashierRole->id);
        }

        // Create Categories (use firstOrCreate to avoid duplicates)
        $categories = [
            ['name' => 'Electronics', 'code' => 'ELEC'],
            ['name' => 'Clothing', 'code' => 'CLTH'],
            ['name' => 'Food & Beverages', 'code' => 'FOOD'],
            ['name' => 'Books', 'code' => 'BOOK'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name']],
                $cat
            );
        }

        // Seed products using ProductSeeder (500 electrical products)
        $this->call(ProductSeeder::class);

        // Create Sample Customers (use firstOrCreate to avoid duplicates)
        $customers = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '555-0101'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone' => '555-0102'],
        ];

        foreach ($customers as $cust) {
            Customer::firstOrCreate(
                ['email' => $cust['email']],
                $cust
            );
        }
    }
}
