<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NonMemberProfile extends Model
{
    protected $fillable = ['uuid', 'user_id', 'name', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
