<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    // Định nghĩa UUID làm khóa chính
    protected $keyType = 'string';
    public $incrementing = false;  // Không tự động tăng cho khóa chính
}

