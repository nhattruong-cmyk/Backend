<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_name',
        'description',
        'start_date',
        'end_date',
        'status',
        'user_id',  // Quản lý dự án
        'department_id',  // Phòng ban chịu trách nhiệm
    ];

    // Liên kết với Department
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Quan hệ 1-n với Task (một dự án có nhiều task)
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'project_task', 'project_id', 'task_id');
    }




}
