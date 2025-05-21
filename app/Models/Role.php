<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    //set up properties
    public $timestamps = false;
    protected $table = 'roles';
    protected $fillable = ['name'];

    // set up relationship
    public function users() {
        return $this->hasMany(User::class, 'role_id', 'id');
    }

    public function run()
    {
        Role::updateOrInsert(
            ['id' => 1],
            ['name' => 'Regular User']
        );
        Role::updateOrInsert(
            ['id' => 2],
            ['name' => 'Admin']
        );
        Role::updateOrInsert(
            ['id' => 3],
            ['name' => 'Super Admin']
        );
    }
}
