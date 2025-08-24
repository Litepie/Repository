<?php

namespace App\Repositories;

use App\Models\User;
use Litepie\Repository\BaseRepository;

/**
 * Example User Repository showing optimization strategies for large datasets
 */
class UserRepository extends BaseRepository
{
    /**
     * Specify the model class name.
     */
    public function model(): string
    {
        return User::class;
    }

    /**
     * Get paginated active users - automatically optimized based on dataset size
     */
    public function getActiveUsers($perPage = 20)
    {
        // Smart pagination automatically chooses the best method
        return $this->where('status', 'active')
            ->where('email_verified_at', '!=', null)
            ->smartPaginate($perPage);
    }

    /**
     * Get users for admin panel - cursor pagination for large datasets
     */
    public function getUsersForAdmin($filters = [], $cursor = null)
    {
        $query = $this->filter($filters);
        
        // Cursor pagination is best for admin panels with large datasets
        return $query->orderBy('id')->cursorPaginate(25, ['*'], 'cursor', $cursor);
    }

    /**
     * Get users feed for infinite scroll - no total count needed
     */
    public function getUsersFeed($page = 1)
    {
        // Fast pagination for infinite scroll - no expensive count query
        return $this->where('status', 'active')
            ->orderByDesc('last_activity_at')
            ->fastPaginate(20);
    }

    /**
     * Search users with optimization for large results
     */
    public function searchUsers($query, $page = 1)
    {
        // For search results, we often don't need exact counts
        return $this->search($query, ['name', 'email', 'username'])
            ->where('status', 'active')
            ->optimizedPaginate(15, ['*'], 'page', $page, true); // Use approximate count
    }

    /**
     * Get user analytics with caching for expensive queries
     */
    public function getUserAnalytics($startDate, $endDate, $page = 1)
    {
        $query = $this->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as registrations,
                COUNT(CASE WHEN email_verified_at IS NOT NULL THEN 1 END) as verified_users
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'desc');

        // Cache expensive analytics queries
        return $query->cachedPaginate(30, ['*'], 'page', $page, 600); // Cache for 10 minutes
    }

    /**
     * Export users efficiently using chunking
     */
    public function exportUsers($filters = [])
    {
        $query = $this->filter($filters);
        
        // Use lazy collection for memory-efficient export
        return $query->lazy(1000)->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Process users in batches
     */
    public function processUsers(callable $processor, $filters = [])
    {
        $query = $this->filter($filters);
        
        // Chunk by ID for better performance on large datasets
        return $query->chunkById(1000, function ($users) use ($processor) {
            foreach ($users as $user) {
                $processor($user);
            }
        });
    }

    /**
     * Get recent user activity for real-time feed
     */
    public function getRecentActivity($lastActivityId = null, $limit = 50)
    {
        // Seek pagination for real-time feeds
        return $this->seekPaginate($limit, $lastActivityId, 'next', 'last_activity_id');
    }

    /**
     * Get top users with heavy aggregation queries
     */
    public function getTopUsers($period = '30days', $page = 1)
    {
        $days = match($period) {
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            default => 30
        };

        $cacheKey = "top_users_{$period}_page_{$page}";
        
        return cache()->remember($cacheKey, 3600, function () use ($days, $page) {
            return $this->selectRaw('
                    users.*,
                    COUNT(posts.id) as posts_count,
                    COUNT(comments.id) as comments_count,
                    SUM(CASE WHEN posts.status = "published" THEN 1 ELSE 0 END) as published_posts
                ')
                ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
                ->leftJoin('comments', 'users.id', '=', 'comments.user_id')
                ->where('users.created_at', '>=', now()->subDays($days))
                ->groupBy('users.id')
                ->having('posts_count', '>', 0)
                ->orderByDesc('posts_count')
                ->paginate(20, ['*'], 'page', $page);
        });
    }

    /**
     * Smart pagination that chooses method based on dataset size
     */
    public function smartPaginate(int $perPage = 15, array $columns = ['*'])
    {
        $estimatedRows = $this->getTableRowEstimate();
        
        if ($estimatedRows > 5000000) {
            // Very large dataset - use cursor pagination
            return $this->orderBy($this->model->getKeyName())
                ->cursorPaginate($perPage, $columns);
        } elseif ($estimatedRows > 1000000) {
            // Large dataset - use fast pagination
            return $this->fastPaginate($perPage, $columns);
        } elseif ($estimatedRows > 100000) {
            // Medium dataset - use optimized pagination
            return $this->optimizedPaginate($perPage, $columns);
        } else {
            // Small dataset - standard pagination is fine
            return $this->paginate($perPage, $columns);
        }
    }

    /**
     * Get table row estimate for smart pagination decisions
     */
    protected function getTableRowEstimate(): int
    {
        $tableName = $this->model->getTable();
        
        try {
            if (config('database.default') === 'mysql') {
                $result = \DB::select("
                    SELECT table_rows 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                ", [$tableName]);
                
                return $result[0]->table_rows ?? 0;
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Run performance benchmark for pagination methods
     */
    public function benchmarkPagination($page = 1, $perPage = 20)
    {
        $results = [];
        
        // Test standard pagination
        $start = microtime(true);
        $standard = $this->where('status', 'active')->paginate($perPage, ['*'], 'page', $page);
        $results['standard'] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_peak_usage(true),
            'total_count' => $standard->total(),
            'method' => 'Standard OFFSET pagination'
        ];
        
        // Test fast pagination
        $this->resetQuery();
        $start = microtime(true);
        $fast = $this->where('status', 'active')->fastPaginate($perPage);
        $results['fast'] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_peak_usage(true),
            'total_count' => 'N/A (no count)',
            'method' => 'Fast pagination (no total count)'
        ];
        
        // Test cursor pagination
        $this->resetQuery();
        $start = microtime(true);
        $cursor = $this->where('status', 'active')->orderBy('id')->cursorPaginate($perPage);
        $results['cursor'] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_peak_usage(true),
            'total_count' => 'N/A (cursor-based)',
            'method' => 'Cursor pagination'
        ];
        
        // Find the fastest method
        $fastest = array_reduce(array_keys($results), function ($carry, $key) use ($results) {
            return $carry === null || $results[$key]['time'] < $results[$carry]['time'] ? $key : $carry;
        });
        
        $results['recommendation'] = [
            'fastest_method' => $fastest,
            'time_saved' => $results['standard']['time'] - $results[$fastest]['time'],
            'estimated_rows' => $this->getTableRowEstimate(),
        ];
        
        return $results;
    }
}
