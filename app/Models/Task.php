<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;
    protected $fillable = ['task_name', 'description', 'status', 'start_date', 'end_date', 'project_id'];
    public function project()
    {
        return $this->belongsToMany(Project::class, 'project_task', 'task_id', 'project_id')
            ->withTimestamps();
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'task_department', 'task_id', 'department_id')
            ->withTimestamps();
    }

    // Quan hệ một-nhiều với bảng Assignment
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    // Quan hệ nhiều-nhiều với User thông qua bảng task_user
    public function users()
    {
        return $this->belongsToMany(User::class, 'task_user')->withTimestamps();
    }
    public function files()
    {
        return $this->hasMany(File::class, 'task_id'); // Liên kết với bảng files qua task_id
    }


    public function getStatusAttribute($value)
    {
        $statuses = [
            0 => 'pending',
            1 => 'in progress',
            2 => 'completed'
        ];

        return $statuses[$value] ?? 'unknown'; // Trả về 'unknown' nếu không tìm thấy giá trị phù hợp
    }

    public function setStatusAttribute($value)
    {
        $statuses = [
            'pending' => 0,
            'in progress' => 1,
            'completed' => 2,
            0 => 0, // Chấp nhận cả số
            1 => 1,
            2 => 2,
        ];

        $this->attributes['status'] = $statuses[$value] ?? 0; // Trạng thái mặc định là 'pending'
    }


}
