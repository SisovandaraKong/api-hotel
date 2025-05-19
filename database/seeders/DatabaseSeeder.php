<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // setup default data
        $dataRoles = [
            ['id' => 1, 'name' => 'Regular User'],
            ['id' => 2, 'name' => 'Admin'],
            ['id' => 3, 'name' => 'Super Admin'],
        ];

        // save default role to db
        foreach ($dataRoles as $dataRole) {
            $role = new Role($dataRole);
            $role->id = $dataRole['id'];
            $role->save();
        }
    }
}
