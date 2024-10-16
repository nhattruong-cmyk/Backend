<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory;
    protected $fillable = ['task_name', 'description', 'status', 'start_date', 'end_date', 'project_id'];
    use SoftDeletes;
    public function projects()
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
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')
            ->withTimestamps();
    }
    public function files()
    {
        return $this->hasMany(File::class, 'task_id'); // Liên kết với bảng files qua task_id
    }

    public function getStatusAttribute($value)
    {
        $statuses = [

            1 => 'to do',
            2 => 'in progress',
            3 => 'preview',
            4 => 'done'
        ];
    
        // Kiểm tra nếu khóa tồn tại trong mảng
        return $statuses[$value] ?? 'unknown'; // Trả về 'unknown' nếu không tìm thấy giá trị phù hợp
    }
    
    // Tạo mutator để lưu status dưới dạng số
    public function setStatusAttribute($value)
    {
        $statuses = [
            'to do' => 1,
            'in progress' => 2,
            'preview' => 3,
            'done' => 4,
            
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,

        ];
    
        // Đặt giá trị status thành 0 (pending) nếu không tìm thấy
        $this->attributes['status'] = $statuses[$value] ?? 1;
    }

}
