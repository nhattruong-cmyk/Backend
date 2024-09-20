<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_name', 'description', 'start_date', 'end_date', 'status', 'user_id',
    ];

        // Tạo accessor để chuyển đổi 'status' từ số sang chuỗi
        public function getStatusAttribute($value)
        {
            $statuses = [
                0 => 'pending',
                1 => 'in progress',
                2 => 'completed'
            ];
        
            // Kiểm tra nếu khóa tồn tại trong mảng
            return $statuses[$value] ?? 'unknown'; // Trả về 'unknown' nếu không tìm thấy giá trị phù hợp
        }
        
    
        // Tạo mutator để lưu status dưới dạng số
        public function setStatusAttribute($value)
        {
            $statuses = [
                'pending' => 0,
                'in progress' => 1,
                'completed' => 2,
                0 => 0, // Thêm cho phép nhận giá trị số
                1 => 1,
                2 => 2,
            ];
        
            // Đặt giá trị status thành 0 (pending) nếu không tìm thấy
            $this->attributes['status'] = $statuses[$value] ?? 0;
        }
        
        public function user()
        {
            return $this->belongsTo(User::class, 'user_id');
        }
        
        public function departments()
        {
            return $this->belongsToMany(Department::class, 'project_department', 'project_id', 'department_id');
        }
        

        // Quan hệ một-nhiều với bảng Task
        public function tasks()
        {
            return $this->hasMany(Task::class);
        }
            
    
        
}
