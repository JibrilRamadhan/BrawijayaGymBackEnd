<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachProfile extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'specialization',
        'certification',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
