<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
