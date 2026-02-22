<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'email',
        'name',
        'phone',
        'username',
        'password',
        'is_guest',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function adminProfile()
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function memberProfile()
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function nonMemberProfile()
    {
        return $this->hasOne(NonMemberProfile::class);
    }

    public function ownerProfile()
    {
        return $this->hasOne(OwnerProfile::class);
    }

    public function coachProfile()
    {
        return $this->hasOne(CoachProfile::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
