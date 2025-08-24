<?php

namespace Litepie\Repository\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'status',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get searchable columns for the model.
     */
    public function getSearchableColumns(): array
    {
        return ['name', 'email'];
    }

    /**
     * Get filterable columns for the model.
     */
    public function getFilterableColumns(): array
    {
        return [
            'name',
            'email',
            'status',
            ['field' => 'created_at', 'operator' => 'date_range'],
        ];
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive users.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope for users with posts.
     */
    public function scopeWithPosts($query)
    {
        return $query->has('posts');
    }

    /**
     * Scope for recent users.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
