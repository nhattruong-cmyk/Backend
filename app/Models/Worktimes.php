<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worktimes extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'user_id', 'start_date', 'end_date', 'description', 'project_id', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Trong model Worktime
    public function tasks()
    {
        return $this->hasMany(Task::class, 'worktime_id');
    }


    public function getStatusAttribute($value)
    {
        $statuses = [

            1 => 'not start',
            2 => 'runing',
            3 => 'conplete',
        ];

        // Kiểm tra nếu khóa tồn tại trong mảng
        return $statuses[$value] ?? 'unknown'; // Trả về 'unknown' nếu không tìm thấy giá trị phù hợp
    }
    public function setStatusAttribute($value)
    {
        $statuses = [
            'not start' => 1,
            'runing' => 2,
            'conplete' => 3,

            1 => 1,
            2 => 2,
            3 => 3,

        ];

        // Đặt giá trị status thành 0 (pending) nếu không tìm thấy
        $this->attributes['status'] = $statuses[$value] ?? 1;
    }
}
