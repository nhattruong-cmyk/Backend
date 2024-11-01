<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'parent_id'];

    // Quan hệ nhiều-nhiều với Role
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

}
