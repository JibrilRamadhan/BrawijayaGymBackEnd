<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminProfile extends Model
{
    protected $fillable = ['uuid', 'user_id', 'phone', 'position'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
