<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'department_id',
        'status',
    ];
    use SoftDeletes;
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
