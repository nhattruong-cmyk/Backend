<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfirmationRequest extends Model
{
    protected $fillable = ['user_id', 'department_id', 'confirmation_token', 'status'];

    // Mối quan hệ với User và Department
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
