<?php

namespace Litepie\Repository\Tests\Repositories;

use Litepie\Repository\BaseRepository;
use Litepie\Repository\Tests\Models\Post;

class PostRepository extends BaseRepository
{
    public function model(): string
    {
        return Post::class;
    }

    /**
     * Get posts with user information using join.
     */
    public function getPostsWithUsers()
    {
        return $this->select(['posts.*', 'users.name as user_name', 'users.email as user_email'])
                   ->join('users', 'posts.user_id', '=', 'users.id')
                   ->where('posts.status', 'published')
                   ->orderBy('posts.created_at', 'desc')
                   ->get();
    }

    /**
     * Get posts with comment count using left join and group by.
     */
    public function getPostsWithCommentCount()
    {
        return $this->select(['posts.*', 'users.name as author_name'])
                   ->selectRaw('COUNT(comments.id) as comments_count')
                   ->join('users', 'posts.user_id', '=', 'users.id')
                   ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
                   ->where('posts.status', 'published')
                   ->groupBy(['posts.id', 'users.name'])
                   ->orderBy('posts.created_at', 'desc')
                   ->get();
    }

    /**
     * Get posts by user with advanced join conditions.
     */
    public function getActivePostsByUser(int $userId)
    {
        return $this->select(['posts.*', 'users.name as author_name'])
                   ->joinWhere('users', function ($join) use ($userId) {
                       $join->on('posts.user_id', '=', 'users.id')
                            ->where('users.id', '=', $userId)
                            ->where('users.status', '=', 'active');
                   })
                   ->where('posts.status', 'published')
                   ->whereNotNull('posts.published_at')
                   ->orderBy('posts.published_at', 'desc')
                   ->get();
    }

    /**
     * Get posts with recent comments using subquery join.
     */
    public function getPostsWithRecentComments()
    {
        $recentComments = $this->makeModel()
                              ->newQuery()
                              ->from('comments')
                              ->select(['post_id', 'content', 'created_at'])
                              ->where('created_at', '>=', now()->subDays(7))
                              ->orderBy('created_at', 'desc');

        return $this->select(['posts.*', 'users.name as author_name', 'recent_comments.content as recent_comment'])
                   ->join('users', 'posts.user_id', '=', 'users.id')
                   ->leftJoinSub($recentComments, 'recent_comments', 'posts.id', '=', 'recent_comments.post_id')
                   ->where('posts.status', 'published')
                   ->orderBy('posts.created_at', 'desc')
                   ->get();
    }

    /**
     * Get popular posts with aggregated data.
     */
    public function getPopularPosts(int $limit = 10)
    {
        return $this->select(['posts.*', 'users.name as author_name'])
                   ->selectRaw('COUNT(comments.id) as comments_count')
                   ->selectRaw('AVG(CASE WHEN comments.status = "approved" THEN 1 ELSE 0 END) * 100 as approval_rate')
                   ->join('users', 'posts.user_id', '=', 'users.id')
                   ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
                   ->where('posts.status', 'published')
                   ->whereDate('posts.published_at', '>=', now()->subDays(30))
                   ->groupBy(['posts.id', 'users.name'])
                   ->having('comments_count', '>', 0)
                   ->orderByRaw('comments_count DESC, approval_rate DESC')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Search posts with user and comment data.
     */
    public function searchPostsWithDetails(string $searchTerm)
    {
        return $this->select(['posts.*', 'users.name as author_name', 'users.email as author_email'])
                   ->selectRaw('COUNT(comments.id) as comments_count')
                   ->join('users', 'posts.user_id', '=', 'users.id')
                   ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
                   ->where(function ($query) use ($searchTerm) {
                       $query->where('posts.title', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('posts.content', 'LIKE', "%{$searchTerm}%")
                             ->orWhere('users.name', 'LIKE', "%{$searchTerm}%");
                   })
                   ->where('posts.status', 'published')
                   ->groupBy(['posts.id', 'users.name', 'users.email'])
                   ->orderBy('posts.created_at', 'desc')
                   ->get();
    }

    /**
     * Get posts by date range with author info.
     */
    public function getPostsByDateRange(string $startDate, string $endDate)
    {
        return $this->select(['posts.*', 'users.name as author_name'])
                   ->join('users', 'posts.user_id', '=', 'users.id')
                   ->whereBetween('posts.created_at', [$startDate, $endDate])
                   ->where('posts.status', 'published')
                   ->orderBy('posts.created_at', 'desc')
                   ->get();
    }
}
