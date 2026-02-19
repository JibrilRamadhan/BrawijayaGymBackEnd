<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberProfile extends Model
{
    protected $fillable = ['uuid', 'user_id', 'first_name', 'last_name', 'middle_name', 'jenis_klamin'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
