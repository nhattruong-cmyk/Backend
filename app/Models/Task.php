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
        return $this->belongsTo(Project::class);
    }
    // Quan hệ nhiều-nhiều với Department
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'task_department');
    }

    


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

}
