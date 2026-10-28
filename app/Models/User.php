<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'about',
        'email',
        'password',
    ];

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
            'password' => 'hashed',
        ];
    }

    /**
     * Get the users that follow user.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(
            related: User::class,
            table: 'user_follow',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'follower_id',
            parentKey: 'id',
            relatedKey: 'id',
        );
    }

    /**
     * Get the users that user follow.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            related: User::class,
            table: 'user_follow',
            foreignPivotKey: 'follower_id',
            relatedPivotKey: 'user_id',
            parentKey: 'id',
            relatedKey: 'id',
        );
    }

    /**
     * Get user stats.
     */
    public function stats(): HasOne
    {
        return $this->hasOne(related: UserStat::class, foreignKey: 'user_id', localKey: 'id');
    }
}
