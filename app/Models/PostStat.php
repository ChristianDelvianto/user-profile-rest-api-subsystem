<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostStat extends Model
{
    /** @use HasFactory<\Database\Factories\PostStatFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_stats';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'post_id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'comments_count',
        'likes_count',
        'shares_count',
        'views_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Get the post that owns the stats.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(
            related: Post::class,
            foreignKey: 'post_id',
            ownerKey: 'id',
        );
    }
}
