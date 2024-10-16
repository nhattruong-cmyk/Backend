<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'loggable_id', 'loggable_type', 'changes', 'timestamp'];

    // protected $casts = [
    //     'changes' => 'array',
    // ];
    
    // Mối quan hệ với bảng User (người thực hiện hành động)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Mối quan hệ morph để lưu trữ dữ liệu của nhiều bảng khác nhau
    public function loggable()
    {
        return $this->morphTo();
    }
}

