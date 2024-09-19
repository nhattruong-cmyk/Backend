<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;
    protected $fillable = [
        'task_name',
        'description',
        'start_date',
        'end_date',
        'status',



    ];
    // Liên kết với Project (n task thuộc về 1 project)

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_task', 'task_id', 'project_id');
    }


    // Quan hệ n-n với User qua bảng Assignment
    public function users()
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id');
    }

}
