<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'role_id',
        'assigned_date',
        'note',
    ];
    // Liên kết với Task
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // Liên kết với User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Liên kết với Role
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
