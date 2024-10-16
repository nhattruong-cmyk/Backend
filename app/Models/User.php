<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Thêm trait này


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // Thêm HasApiTokens vào đây

    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id'
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
        ];
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
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
}
