<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;
    protected $fillable = ['task_id', 'user_id', 'comment', 'parent_id'];

    // Một bình luận thuộc về một task
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    // Một bình luận thuộc về một người dùng
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Một bình luận có thể có nhiều phản hồi (comments con)
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->with('replies');
    }

    // Bình luận cha của phản hồi
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
}
