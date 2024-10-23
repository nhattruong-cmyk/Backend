<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['file_name', 'file_path', 'task_id', 'comment_id', 'uploaded_by'];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id'); // Thuộc về một Task
    }
    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    // Mối quan hệ giữa File và User
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
