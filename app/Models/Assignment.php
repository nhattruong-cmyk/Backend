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
        'department_id',
        'status',
    ];
    // Quan hệ với Task
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function getStatusAttribute($value)
    {
        $statuses = [
            0 => 'pending',
            1 => 'in progress',
            2 => 'completed'
        ];

        return $statuses[$value] ?? 'unknown';
    }

    /**
     * Mutator để lưu 'status' dưới dạng số
     */
    public function setStatusAttribute($value)
    {
        $statuses = [
            'pending' => 0,
            'in progress' => 1,
            'completed' => 2,
            0 => 0,
            1 => 1,
            2 => 2,
        ];

        $this->attributes['status'] = $statuses[$value] ?? 0;
    }
}
