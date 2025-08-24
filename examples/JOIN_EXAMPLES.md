# Join Features Examples

This document demonstrates the comprehensive join capabilities of the Litepie Repository package.

## Table of Contents

- [Basic Joins](#basic-joins)
- [Advanced Join Conditions](#advanced-join-conditions)
- [Subquery Joins](#subquery-joins)
- [Aggregation with Joins](#aggregation-with-joins)
- [Complex Query Examples](#complex-query-examples)

## Basic Joins

### Inner Join
```php
// Get posts with author information
$posts = $postRepository
    ->select(['posts.*', 'users.name as author_name', 'users.email'])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.status', 'published')
    ->get();
```

### Left Join
```php
// Get all posts with optional comment count
$posts = $postRepository
    ->select(['posts.*', 'users.name as author_name'])
    ->selectRaw('COUNT(comments.id) as comments_count')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->groupBy(['posts.id', 'users.name'])
    ->get();
```

### Right Join
```php
// Get all users with their latest post (if any)
$usersWithPosts = $userRepository
    ->select(['users.*', 'posts.title as latest_post_title'])
    ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
    ->whereRaw('posts.id = (SELECT MAX(id) FROM posts WHERE user_id = users.id)')
    ->get();
```

### Cross Join
```php
// Create combinations (use sparingly with large datasets)
$combinations = $repository
    ->select(['table1.*', 'table2.*'])
    ->crossJoin('categories')
    ->limit(100)
    ->get();
```

## Advanced Join Conditions

### Join with Closure Conditions
```php
// Join with multiple conditions
$posts = $postRepository
    ->select(['posts.*', 'users.name as author_name'])
    ->joinWhere('users', function ($join) {
        $join->on('posts.user_id', '=', 'users.id')
             ->where('users.status', '=', 'active')
             ->where('users.created_at', '>', now()->subYear());
    })
    ->get();
```

### Multiple Table Joins
```php
// Join multiple tables
$postsWithDetails = $postRepository
    ->select([
        'posts.*', 
        'users.name as author_name',
        'categories.name as category_name',
        'tags.name as tag_name'
    ])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->join('categories', 'posts.category_id', '=', 'categories.id')
    ->leftJoin('post_tags', 'posts.id', '=', 'post_tags.post_id')
    ->leftJoin('tags', 'post_tags.tag_id', '=', 'tags.id')
    ->where('posts.status', 'published')
    ->orderBy('posts.created_at', 'desc')
    ->get();
```

## Subquery Joins

### Basic Subquery Join
```php
// Join with a subquery to get latest comments
$latestComments = Comment::select(['post_id', 'content', 'created_at'])
    ->whereRaw('id = (SELECT MAX(id) FROM comments c WHERE c.post_id = comments.post_id)')
    ->toBase();

$postsWithLatestComments = $postRepository
    ->select(['posts.*', 'users.name as author', 'latest_comments.content as latest_comment'])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->leftJoinSub($latestComments, 'latest_comments', 'posts.id', '=', 'latest_comments.post_id')
    ->get();
```

### Complex Subquery with Aggregation
```php
// Join with aggregated data from subquery
$postStats = Post::select(['user_id'])
    ->selectRaw('COUNT(*) as post_count')
    ->selectRaw('AVG(view_count) as avg_views')
    ->selectRaw('MAX(created_at) as latest_post')
    ->where('status', 'published')
    ->groupBy('user_id')
    ->toBase();

$usersWithStats = $userRepository
    ->select(['users.*', 'post_stats.*'])
    ->leftJoinSub($postStats, 'post_stats', 'users.id', '=', 'post_stats.user_id')
    ->orderBy('post_stats.post_count', 'desc')
    ->get();
```

## Aggregation with Joins

### Group By with Having
```php
// Get popular posts with high engagement
$popularPosts = $postRepository
    ->select(['posts.*', 'users.name as author'])
    ->selectRaw('COUNT(DISTINCT comments.id) as comment_count')
    ->selectRaw('COUNT(DISTINCT likes.id) as like_count')
    ->selectRaw('(COUNT(DISTINCT comments.id) + COUNT(DISTINCT likes.id)) as engagement_score')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->leftJoin('likes', 'posts.id', '=', 'likes.post_id')
    ->where('posts.status', 'published')
    ->whereDate('posts.created_at', '>=', now()->subDays(30))
    ->groupBy(['posts.id', 'users.name'])
    ->having('engagement_score', '>', 10)
    ->orderByRaw('engagement_score DESC')
    ->get();
```

### Advanced Aggregation
```php
// Get user statistics with multiple metrics
$userStats = $userRepository
    ->select(['users.*'])
    ->selectRaw('COUNT(DISTINCT posts.id) as total_posts')
    ->selectRaw('COUNT(DISTINCT CASE WHEN posts.status = "published" THEN posts.id END) as published_posts')
    ->selectRaw('COUNT(DISTINCT comments.id) as total_comments')
    ->selectRaw('AVG(posts.view_count) as avg_post_views')
    ->selectRaw('MAX(posts.created_at) as latest_post_date')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->leftJoin('comments', 'users.id', '=', 'comments.user_id')
    ->groupBy('users.id')
    ->havingRaw('COUNT(DISTINCT posts.id) > 0')
    ->orderBy('total_posts', 'desc')
    ->get();
```

## Complex Query Examples

### Search Across Multiple Tables
```php
public function searchContent(string $term, array $filters = [])
{
    $query = $this->select([
            'posts.*', 
            'users.name as author_name',
            'categories.name as category_name'
        ])
        ->selectRaw('COUNT(DISTINCT comments.id) as comment_count')
        ->join('users', 'posts.user_id', '=', 'users.id')
        ->leftJoin('categories', 'posts.category_id', '=', 'categories.id')
        ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
        ->where(function($q) use ($term) {
            $q->where('posts.title', 'LIKE', "%{$term}%")
              ->orWhere('posts.content', 'LIKE', "%{$term}%")
              ->orWhere('users.name', 'LIKE', "%{$term}%")
              ->orWhere('categories.name', 'LIKE', "%{$term}%");
        });

    if (!empty($filters['category_id'])) {
        $query->where('posts.category_id', $filters['category_id']);
    }

    if (!empty($filters['date_from'])) {
        $query->whereDate('posts.created_at', '>=', $filters['date_from']);
    }

    if (!empty($filters['min_comments'])) {
        $query->havingRaw('COUNT(DISTINCT comments.id) >= ?', [$filters['min_comments']]);
    }

    return $query->groupBy(['posts.id', 'users.name', 'categories.name'])
                 ->orderBy('posts.created_at', 'desc')
                 ->paginate(15);
}
```

### Analytics Query with Time Periods
```php
public function getEngagementAnalytics(string $period = 'month')
{
    $dateFormat = match($period) {
        'day' => '%Y-%m-%d',
        'week' => '%Y-%u',
        'month' => '%Y-%m',
        'year' => '%Y',
        default => '%Y-%m'
    };

    return $this->select([])
        ->selectRaw("DATE_FORMAT(posts.created_at, '{$dateFormat}') as period")
        ->selectRaw('COUNT(DISTINCT posts.id) as posts_count')
        ->selectRaw('COUNT(DISTINCT comments.id) as comments_count')
        ->selectRaw('COUNT(DISTINCT likes.id) as likes_count')
        ->selectRaw('COUNT(DISTINCT users.id) as active_authors')
        ->selectRaw('AVG(posts.view_count) as avg_views')
        ->join('users', 'posts.user_id', '=', 'users.id')
        ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
        ->leftJoin('likes', 'posts.id', '=', 'likes.post_id')
        ->where('posts.status', 'published')
        ->whereDate('posts.created_at', '>=', now()->subYear())
        ->groupByRaw("DATE_FORMAT(posts.created_at, '{$dateFormat}')")
        ->orderByRaw("DATE_FORMAT(posts.created_at, '{$dateFormat}') DESC")
        ->get();
}
```

### Recommendations Query
```php
public function getRecommendedPosts(int $userId, int $limit = 10)
{
    // Get posts from users that the current user interacts with most
    $userInteractions = $this->select([])
        ->selectRaw('posts.user_id')
        ->selectRaw('COUNT(*) as interaction_score')
        ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
        ->leftJoin('likes', 'posts.id', '=', 'likes.post_id')
        ->where(function($q) use ($userId) {
            $q->where('comments.user_id', $userId)
              ->orWhere('likes.user_id', $userId);
        })
        ->groupBy('posts.user_id')
        ->orderBy('interaction_score', 'desc')
        ->limit(20)
        ->toBase();

    return $this->select(['posts.*', 'users.name as author_name'])
        ->selectRaw('user_interactions.interaction_score')
        ->selectRaw('COUNT(DISTINCT comments.id) as total_comments')
        ->selectRaw('COUNT(DISTINCT likes.id) as total_likes')
        ->join('users', 'posts.user_id', '=', 'users.id')
        ->joinSub($userInteractions, 'user_interactions', 'posts.user_id', '=', 'user_interactions.user_id')
        ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
        ->leftJoin('likes', 'posts.id', '=', 'likes.post_id')
        ->where('posts.status', 'published')
        ->where('posts.user_id', '!=', $userId)
        ->whereDate('posts.created_at', '>=', now()->subDays(30))
        ->groupBy(['posts.id', 'users.name', 'user_interactions.interaction_score'])
        ->orderByRaw('(user_interactions.interaction_score * 0.4 + COUNT(DISTINCT likes.id) * 0.6) DESC')
        ->limit($limit)
        ->get();
}
```

## Performance Tips

1. **Index your join columns** - Always ensure foreign keys and join columns are indexed
2. **Use appropriate join types** - Use `LEFT JOIN` when you need all records from the left table
3. **Limit result sets** - Use `limit()` and `offset()` for pagination
4. **Select only needed columns** - Use `select()` to avoid selecting unnecessary data
5. **Use `EXPLAIN`** - Debug slow queries with `DB::getQueryLog()` or Laravel Debugbar
6. **Consider eager loading** - For relationship data, sometimes `with()` is more efficient than joins

## Repository Implementation Example

```php
class PostRepository extends BaseRepository
{
    public function model(): string
    {
        return Post::class;
    }

    public function findWithAuthorAndComments(int $id)
    {
        return $this->select(['posts.*', 'users.name as author_name'])
            ->selectRaw('COUNT(comments.id) as comments_count')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
            ->where('posts.id', $id)
            ->groupBy(['posts.id', 'users.name'])
            ->first();
    }

    public function getPopularInCategory(int $categoryId, int $days = 30)
    {
        return $this->select(['posts.*', 'users.name as author'])
            ->selectRaw('(COUNT(DISTINCT likes.id) + COUNT(DISTINCT comments.id) * 2) as popularity_score')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->leftJoin('likes', 'posts.id', '=', 'likes.post_id')
            ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
            ->where('posts.category_id', $categoryId)
            ->where('posts.status', 'published')
            ->whereDate('posts.created_at', '>=', now()->subDays($days))
            ->groupBy(['posts.id', 'users.name'])
            ->orderBy('popularity_score', 'desc')
            ->paginate(15);
    }
}
```

These examples showcase the full power of the join features in the Litepie Repository package, enabling you to build complex, efficient database queries while maintaining clean, readable code.
