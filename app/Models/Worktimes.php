<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worktimes extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name','user_id', 'start_date', 'end_date', 'description', 'project_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'worktime_id');
    }
    
}
