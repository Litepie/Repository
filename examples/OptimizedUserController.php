<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Example Controller showing optimized pagination usage
 */
class OptimizedUserController extends Controller
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Admin user listing with cursor pagination for large datasets
     */
    public function adminIndex(Request $request)
    {
        $filters = $request->only(['status', 'role', 'search']);
        $cursor = $request->get('cursor');
        
        // Cursor pagination - best for large datasets in admin panels
        $users = $this->userRepository->getUsersForAdmin($filters, $cursor);
        
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $users->items(),
                'next_cursor' => $users->nextCursor()?->encode(),
                'prev_cursor' => $users->previousCursor()?->encode(),
                'has_more' => $users->hasMorePages(),
            ]);
        }
        
        return view('admin.users.index', compact('users', 'filters'));
    }

    /**
     * Public user listing with fast pagination (no total count)
     */
    public function publicIndex(Request $request)
    {
        $page = $request->get('page', 1);
        
        // Fast pagination - no expensive count query
        $users = $this->userRepository->getUsersFeed($page);
        
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $users->items(),
                'current_page' => $users->currentPage(),
                'has_more_pages' => $users->hasMorePages(),
                'per_page' => $users->perPage(),
            ]);
        }
        
        return view('users.index', compact('users'));
    }

    /**
     * User search with optimized pagination
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $page = $request->get('page', 1);
        
        if (empty($query)) {
            return response()->json(['data' => [], 'message' => 'Query required']);
        }
        
        // Optimized pagination with approximate count for search results
        $users = $this->userRepository->searchUsers($query, $page);
        
        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(), // Approximate count
                'last_page' => $users->lastPage(),
                'has_more_pages' => $users->hasMorePages(),
            ],
            'meta' => [
                'query' => $query,
                'total_approximate' => true,
            ]
        ]);
    }

    /**
     * Infinite scroll API endpoint
     */
    public function infiniteScroll(Request $request): JsonResponse
    {
        $lastId = $request->get('last_id');
        $limit = min($request->get('limit', 20), 100); // Limit max to 100
        
        // Seek pagination for infinite scroll
        $users = $this->userRepository->getRecentActivity($lastId, $limit);
        
        return response()->json([
            'data' => $users,
            'pagination' => [
                'has_more' => $users->count() === $limit,
                'last_id' => $users->last()?->id,
                'count' => $users->count(),
            ]
        ]);
    }

    /**
     * Analytics dashboard with cached heavy queries
     */
    public function analytics(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $page = $request->get('page', 1);
        
        // Cached pagination for expensive analytics queries
        $analytics = $this->userRepository->getUserAnalytics($startDate, $endDate, $page);
        
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $analytics->items(),
                'pagination' => [
                    'current_page' => $analytics->currentPage(),
                    'per_page' => $analytics->perPage(),
                    'total' => $analytics->total(),
                    'last_page' => $analytics->lastPage(),
                ],
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ]);
        }
        
        return view('admin.analytics.users', compact('analytics', 'startDate', 'endDate'));
    }

    /**
     * Export users with memory-efficient processing
     */
    public function export(Request $request)
    {
        $filters = $request->only(['status', 'role', 'created_after', 'created_before']);
        
        // Stream the export to handle large datasets
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_' . date('Y-m-d') . '.csv"',
        ];
        
        return response()->stream(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            
            // Write CSV header
            fputcsv($handle, ['ID', 'Name', 'Email', 'Status', 'Created At']);
            
            // Use lazy collection for memory-efficient export
            $users = $this->userRepository->exportUsers($filters);
            
            foreach ($users as $user) {
                fputcsv($handle, [
                    $user['id'],
                    $user['name'],
                    $user['email'],
                    $user['status'],
                    $user['created_at'],
                ]);
            }
            
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Performance benchmark endpoint for testing
     */
    public function benchmark(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        
        // Only allow in development/testing
        if (!app()->environment(['local', 'testing'])) {
            abort(403, 'Benchmark endpoint only available in development');
        }
        
        $benchmark = $this->userRepository->benchmarkPagination($page, $perPage);
        
        return response()->json([
            'benchmark_results' => $benchmark,
            'recommendations' => [
                'fastest_method' => $benchmark['recommendation']['fastest_method'],
                'performance_gain' => round($benchmark['recommendation']['time_saved'] * 1000, 2) . 'ms saved',
                'dataset_size' => number_format($benchmark['recommendation']['estimated_rows']) . ' estimated rows',
            ]
        ]);
    }

    /**
     * Smart pagination that automatically chooses the best method
     */
    public function smartIndex(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        
        // Smart pagination automatically chooses optimal method
        $users = $this->userRepository
            ->where('status', 'active')
            ->smartPaginate($perPage);
        
        // Detect pagination type for frontend handling
        $paginationType = match(get_class($users)) {
            'Illuminate\Pagination\CursorPaginator' => 'cursor',
            'Illuminate\Contracts\Pagination\Paginator' => 'simple',
            default => 'standard'
        };
        
        if ($request->expectsJson()) {
            $response = [
                'data' => $users->items(),
                'pagination_type' => $paginationType,
            ];
            
            // Add appropriate pagination metadata based on type
            switch ($paginationType) {
                case 'cursor':
                    $response['pagination'] = [
                        'next_cursor' => $users->nextCursor()?->encode(),
                        'prev_cursor' => $users->previousCursor()?->encode(),
                        'has_more' => $users->hasMorePages(),
                    ];
                    break;
                    
                case 'simple':
                    $response['pagination'] = [
                        'current_page' => $users->currentPage(),
                        'has_more_pages' => $users->hasMorePages(),
                        'per_page' => $users->perPage(),
                    ];
                    break;
                    
                default:
                    $response['pagination'] = [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                        'has_more_pages' => $users->hasMorePages(),
                    ];
            }
            
            return response()->json($response);
        }
        
        return view('users.smart-index', compact('users', 'paginationType'));
    }

    /**
     * Process users in batches for background jobs
     */
    public function processBatch(Request $request): JsonResponse
    {
        $batchSize = $request->get('batch_size', 1000);
        $filters = $request->only(['status', 'role']);
        $processed = 0;
        
        // Process users in chunks to avoid memory issues
        $this->userRepository->processUsers(function ($user) use (&$processed) {
            // Your processing logic here
            // e.g., send email, update status, etc.
            $processed++;
        }, $filters);
        
        return response()->json([
            'message' => 'Batch processing completed',
            'processed_count' => $processed,
            'batch_size' => $batchSize,
        ]);
    }
}
