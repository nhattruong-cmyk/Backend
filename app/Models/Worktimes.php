<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worktimes extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = ['name', 'start_date', 'end_date', 'description', 'delete_at'];
}
