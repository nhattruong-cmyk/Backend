<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Thêm trait này
use App\Models\Worktimes;
use Illuminate\Contracts\Auth\MustVerifyEmail;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable; // Thêm HasApiTokens vào đây

    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fullname',
        'email',
        'password',
        'avatar',
        'role_id',
        'phone_number',
        'verification_code',
        'verification_code_expires_at', // Thêm trường này
        'otp_code', // Thêm otp_code vào đây
        'otp_expires_at', // Thêm trường này
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'verification_code_expires_at' => 'datetime', // Chuyển đổi trường thành Carbon
            'otp_expires_at' => 'datetime', // Chuyển đổi trường thành Carbon
        ];
    }
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
    public function hasRole($role)
    {
        return $this->role && $this->role->name === $role; // Kiểm tra vai trò
    }
    // Hàm kiểm tra quyền của người dùng
    public function hasPermission($permissionName)
    {
        return $this->role && $this->role->permissions->contains('name', $permissionName);
    }
    // Quan hệ nhiều-nhiều với Department
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user', 'user_id', 'department_id')
            ->withTimestamps();
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    // Quan hệ nhiều-nhiều với Task thông qua bảng phụ task_user
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_user', 'user_id', 'task_id')
            ->withTimestamps();
    }

    // Quan hệ một-nhiều với Assignment
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
    // Mối quan hệ với bảng activity_logs
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function worktimes()
    {
        return $this->hasMany(Worktimes::class, 'user_id');
    }
}
