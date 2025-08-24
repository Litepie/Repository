<?php

namespace Litepie\Repository\Tests\Repositories;

use Litepie\Repository\BaseRepository;
use Litepie\Repository\Tests\Models\User;

class UserRepository extends BaseRepository
{
    public function model(): string
    {
        return User::class;
    }

    public function findActiveUsers()
    {
        return $this->where('status', 'active')->get();
    }

    public function findByEmail(string $email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Filter users with advanced conditions.
     */
    public function filterUsers(array $filters)
    {
        return $this->filter($filters)->get();
    }

    /**
     * Search users by name or email.
     */
    public function searchUsers(string $term)
    {
        return $this->search($term, ['name', 'email'])->get();
    }

    /**
     * Get users with post count filter.
     */
    public function getUsersWithPostCount(int $minPosts = 1)
    {
        return $this->select(['users.*'])
                   ->selectRaw('COUNT(posts.id) as posts_count')
                   ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
                   ->groupBy('users.id')
                   ->having('posts_count', '>=', $minPosts)
                   ->get();
    }

    /**
     * Filter users by date range.
     */
    public function getUsersByDateRange(string $from, string $to)
    {
        return $this->dateRange('created_at', $from, $to)->get();
    }

    /**
     * Get users with conditional filters.
     */
    public function getConditionalUsers(array $conditions)
    {
        return $this->when(!empty($conditions['status']), function ($query) use ($conditions) {
                       $query->where('status', $conditions['status']);
                   })
                   ->when(!empty($conditions['search']), function ($query) use ($conditions) {
                       $query->search($conditions['search']);
                   })
                   ->when(!empty($conditions['has_posts']), function ($query) {
                       $query->has('posts');
                   })
                   ->get();
    }

    /**
     * Filter users from request data.
     */
    public function getUsersFromRequest(array $requestData)
    {
        $allowedFilters = [
            'name',
            'email',
            'status',
            ['field' => 'created_at', 'operator' => 'date_range', 'request_key' => 'date_range'],
        ];

        return $this->filterFromRequest($requestData, $allowedFilters)
                   ->sortFromRequest($requestData, ['name', 'email', 'created_at'])
                   ->paginate(15);
    }

    /**
     * Advanced filter with nested conditions.
     */
    public function advancedUserFilter(array $filters)
    {
        return $this->advancedFilter($filters)->get();
    }

    /**
     * Filter users by relationship.
     */
    public function filterByPosts(array $postFilters)
    {
        return $this->filterByRelation('posts', $postFilters)->get();
    }

    /**
     * Search users with ranking.
     */
    public function searchUsersWithRanking(string $term)
    {
        return $this->searchWithRanking($term, ['name', 'email'], [2, 1])->get();
    }

    /**
     * Get filtered users with statistics.
     */
    public function getFilteredUsersWithStats(array $filters)
    {
        return $this->getFilteredWithCount($filters);
    }

    /**
     * Dynamic filter based on configuration.
     */
    public function dynamicUserFilter(array $requestData)
    {
        $filterConfig = [
            [
                'field' => 'name',
                'request_key' => 'name',
                'operator' => 'like',
            ],
            [
                'field' => 'email',
                'request_key' => 'email',
                'operator' => 'like',
            ],
            [
                'field' => 'status',
                'request_key' => 'status',
                'operator' => 'in',
                'transform' => 'array',
            ],
            [
                'field' => 'created_at',
                'request_key' => 'created_from',
                'operator' => '>=',
                'transform' => 'date',
            ],
            [
                'field' => 'created_at',
                'request_key' => 'created_to',
                'operator' => '<=',
                'transform' => 'date',
            ],
        ];

        return $this->dynamicFilter($requestData, $filterConfig)->paginate(15);
    }
}
