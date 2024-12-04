<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'department_name',
        'description',
    ];


    // Quan hệ nhiều-nhiều giữa Department và User
    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user', 'department_id', 'user_id')
            ->withTimestamps();  // Ghi lại thời gian tạo và cập nhật
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_department', 'department_id', 'project_id');
    }
    // Quan hệ nhiều-nhiều với Task
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_department', 'department_id', 'task_id')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function hasPermission($roleId)
    {
        // Nếu người dùng có vai trò 1, 2 hoặc 3, sẽ có quyền thao tác với phòng ban
        return in_array($roleId, [1, 2, 3]);
    }

    // Trong Department model
    public function staff()
    {
        return $this->users(); 
    }


}
