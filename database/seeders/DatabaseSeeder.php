<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Demo logins (password for all: "password"):
     *  - admin@srolanh.com      super_admin
     *  - organizer@srolanh.com  organizer
     *  - sophea@srolanh.com     couple (bride)
     *  - visal@srolanh.com      couple (groom)
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CatalogSeeder::class,
            DemoWeddingSeeder::class,
        ]);
    }
}
