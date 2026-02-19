<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnerProfile extends Model
{
    protected $fillable = ['uuid', 'user_id', 'name', 'business_name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
