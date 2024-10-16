<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'department_name',
        'description',
    ];

    // Quan hệ nhiều-nhiều giữa Department và User
    public function users()
    {
        return $this->belongsToMany(User::class, 'department_user', 'department_id', 'user_id')
            ->withTimestamps();
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
}
