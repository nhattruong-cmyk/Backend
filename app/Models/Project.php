<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'project_name', 'description', 'start_date', 'end_date', 'status', 'user_id',
    ];

        // Tạo accessor để chuyển đổi 'status' từ số sang chuỗi
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
        
        public function user()
        {
            return $this->belongsTo(User::class, 'user_id');
        }
        
        public function departments()
        {
            return $this->belongsToMany(Department::class, 'project_department', 'project_id', 'department_id');
        }
        
        public function tasks()
        {
            return $this->belongsToMany(Task::class, 'project_task', 'project_id', 'task_id')
                        ->withTimestamps();
        }
        
}
