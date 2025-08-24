<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\PropertyRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Litepie\Repository\Traits\QueryStringParser;

/**
 * Property API Controller demonstrating query string filter usage
 */
class PropertyController extends Controller
{
    public function __construct(
        private PropertyRepositoryInterface $propertyRepository
    ) {}

    /**
     * Search properties using query string filters.
     * 
     * Example URLs:
     * /api/properties?filters=category:IN(Apartment,Bungalow);price:BETWEEN(100000,500000);status:EQ(Published)
     * /api/properties?filters=bua:BETWEEN(1000,3000);sbeds:IN(1,2,3);rental_period:EQ(monthly)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filterString = $request->get('filters', '');
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 20), 100);
            $sort = $request->get('sort', 'date_desc');

            // Validate filter string if provided
            if (!empty($filterString)) {
                $validation = QueryStringParser::validateFilterString($filterString);
                if (!$validation['valid']) {
                    return response()->json([
                        'error' => 'Invalid filter format',
                        'details' => $validation['errors']
                    ], 400);
                }
            }

            // Apply filters and get results
            $properties = $this->propertyRepository
                ->searchWithFilters($filterString, ['sort' => $sort])
                ->with(['images', 'location', 'agent'])
                ->cursorPaginate($perPage);

            // Get filter summary for display
            $filterSummary = $this->propertyRepository->getFilterSummary($filterString);

            return response()->json([
                'data' => $properties->items(),
                'pagination' => [
                    'next_cursor' => $properties->nextCursor()?->encode(),
                    'prev_cursor' => $properties->previousCursor()?->encode(),
                    'has_more' => $properties->hasMorePages(),
                    'per_page' => $properties->perPage(),
                ],
                'filters' => [
                    'applied' => $filterSummary,
                    'string' => $filterString,
                ],
                'meta' => [
                    'sort' => $sort,
                    'total_estimated' => $this->propertyRepository->estimatedCount(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Advanced property search with multiple filter options.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'sometimes|string|max:255',
            'filters' => 'sometimes|string',
            'category' => 'sometimes|array',
            'category.*' => 'string',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'min_area' => 'sometimes|numeric|min:0',
            'max_area' => 'sometimes|numeric|min:0',
            'location' => 'sometimes|string|max:255',
            'bedrooms' => 'sometimes|array',
            'bedrooms.*' => 'integer|min:0',
            'sort' => 'sometimes|string|in:price_asc,price_desc,area_asc,area_desc,date_asc,date_desc,relevance',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            // Start with advanced search
            $query = $this->propertyRepository->advancedSearch($validated);

            // Add text search if provided
            if (!empty($validated['q'])) {
                $query->search($validated['q'], ['title', 'description', 'location']);
            }

            // Get results with optimized pagination
            $properties = $query->with(['images', 'location', 'agent'])
                ->optimizedPaginate($validated['per_page'] ?? 20);

            return response()->json([
                'data' => $properties->items(),
                'pagination' => [
                    'current_page' => $properties->currentPage(),
                    'per_page' => $properties->perPage(),
                    'total' => $properties->total(),
                    'last_page' => $properties->lastPage(),
                    'has_more_pages' => $properties->hasMorePages(),
                ],
                'search' => [
                    'query' => $validated['q'] ?? null,
                    'applied_filters' => $this->getAppliedFilters($validated),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filter options for frontend filter builder.
     */
    public function filterOptions(): JsonResponse
    {
        return response()->json([
            'operators' => QueryStringParser::getAvailableOperators(),
            'fields' => [
                'category' => [
                    'type' => 'select',
                    'multiple' => true,
                    'options' => ['Apartment', 'Villa', 'Townhouse', 'Penthouse', 'Studio'],
                    'operators' => ['IN', 'NOT_IN', 'EQ']
                ],
                'price' => [
                    'type' => 'number',
                    'operators' => ['BETWEEN', 'GT', 'GTE', 'LT', 'LTE', 'EQ']
                ],
                'bua' => [
                    'type' => 'number',
                    'label' => 'Built-up Area (sq ft)',
                    'operators' => ['BETWEEN', 'GT', 'GTE', 'LT', 'LTE']
                ],
                'sbeds' => [
                    'type' => 'select',
                    'multiple' => true,
                    'label' => 'Bedrooms',
                    'options' => ['Studio', '1', '2', '3', '4', '5+'],
                    'operators' => ['IN', 'NOT_IN', 'EQ']
                ],
                'status' => [
                    'type' => 'select',
                    'options' => ['Published', 'Draft', 'Archived'],
                    'operators' => ['IN', 'EQ']
                ],
                'rental_period' => [
                    'type' => 'select',
                    'options' => ['daily', 'weekly', 'monthly', 'yearly'],
                    'operators' => ['IN', 'EQ']
                ],
                'furnished' => [
                    'type' => 'select',
                    'options' => ['yes', 'no', 'partial'],
                    'operators' => ['EQ', 'IN']
                ],
                'created_at' => [
                    'type' => 'date',
                    'operators' => ['DATE_EQ', 'DATE_GT', 'DATE_GTE', 'DATE_LT', 'DATE_LTE', 'DATE_BETWEEN']
                ]
            ],
            'examples' => [
                'basic' => 'category:IN(Apartment,Villa);price:BETWEEN(100000,500000)',
                'complex' => 'category:IN(Apartment,Villa);price:BETWEEN(100000,500000);bua:GT(1000);status:EQ(Published);created_at:DATE_BETWEEN(2023-01-01,2023-12-31)',
                'real_estate' => 'category:IN(Apartment,Bungalow);leads:IN(1,3);manager_of:IN(1449282);status:IN(Published);bua:BETWEEN(5000,3000);rental_period:IN(monthly);sbeds:IN(1,2,3);portals:IN(bayut)',
            ]
        ]);
    }

    /**
     * Build filter URL for sharing or bookmarking.
     */
    public function buildFilterUrl(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => 'sometimes|array',
            'price_range' => 'sometimes|array|size:2',
            'area_range' => 'sometimes|array|size:2',
            'bedrooms' => 'sometimes|array',
            'status' => 'sometimes|string',
            'location' => 'sometimes|string',
        ]);

        $filterConditions = [];

        // Convert array filters to filter conditions
        foreach ($filters as $field => $value) {
            switch ($field) {
                case 'price_range':
                case 'area_range':
                    if (count($value) === 2) {
                        $filterField = $field === 'price_range' ? 'price' : 'bua';
                        $filterConditions[$filterField] = [
                            'operator' => 'BETWEEN',
                            'values' => [(int)$value[0], (int)$value[1]]
                        ];
                    }
                    break;

                case 'category':
                case 'bedrooms':
                    if (is_array($value) && !empty($value)) {
                        $filterConditions[$field] = [
                            'operator' => 'IN',
                            'values' => $value
                        ];
                    }
                    break;

                case 'status':
                case 'location':
                    if (!empty($value)) {
                        $filterConditions[$field] = [
                            'operator' => $field === 'location' ? 'LIKE' : 'EQ',
                            'values' => [$value]
                        ];
                    }
                    break;
            }
        }

        $filterString = QueryStringParser::buildFilterString($filterConditions);
        $url = url('/api/properties?filters=' . urlencode($filterString));

        return response()->json([
            'filter_string' => $filterString,
            'url' => $url,
            'filters_applied' => count($filterConditions),
        ]);
    }

    /**
     * Validate filter string format.
     */
    public function validateFilters(Request $request): JsonResponse
    {
        $request->validate([
            'filters' => 'required|string'
        ]);

        $filterString = $request->get('filters');
        $validation = QueryStringParser::validateFilterString($filterString);

        return response()->json([
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
            'filter_string' => $filterString,
        ]);
    }

    /**
     * Get popular filter combinations for autocomplete.
     */
    public function popularFilters(): JsonResponse
    {
        // This could be cached and updated periodically
        $popularFilters = [
            [
                'name' => 'Luxury Apartments',
                'filter' => 'category:EQ(Apartment);price:GT(500000);bua:GT(1500)',
                'description' => 'High-end apartments over 1500 sq ft'
            ],
            [
                'name' => 'Family Villas',
                'filter' => 'category:EQ(Villa);sbeds:IN(3,4,5);bua:BETWEEN(2000,5000)',
                'description' => 'Spacious villas perfect for families'
            ],
            [
                'name' => 'Investment Properties',
                'filter' => 'status:EQ(Published);rental_period:IN(monthly,yearly);price:BETWEEN(200000,800000)',
                'description' => 'Properties suitable for rental investment'
            ],
            [
                'name' => 'Budget Friendly',
                'filter' => 'price:LT(300000);category:IN(Apartment,Studio)',
                'description' => 'Affordable apartments and studios'
            ],
        ];

        return response()->json($popularFilters);
    }

    /**
     * Export filtered properties (CSV/Excel).
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'filters' => 'sometimes|string',
            'format' => 'sometimes|string|in:csv,excel',
        ]);

        $filterString = $validated['filters'] ?? '';
        $format = $validated['format'] ?? 'csv';

        // Validate filters
        if (!empty($filterString)) {
            $validation = QueryStringParser::validateFilterString($filterString);
            if (!$validation['valid']) {
                return response()->json([
                    'error' => 'Invalid filter format',
                    'details' => $validation['errors']
                ], 400);
            }
        }

        $headers = [
            'Content-Type' => $format === 'csv' ? 'text/csv' : 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="properties_' . date('Y-m-d') . '.' . $format . '"',
        ];

        return response()->stream(function () use ($filterString) {
            $handle = fopen('php://output', 'w');
            
            // CSV header
            fputcsv($handle, [
                'ID', 'Title', 'Category', 'Price', 'Area (sq ft)', 
                'Bedrooms', 'Status', 'Location', 'Created At'
            ]);

            // Use lazy loading for memory efficiency
            $properties = $this->propertyRepository
                ->searchWithFilters($filterString)
                ->lazy(1000);

            foreach ($properties as $property) {
                fputcsv($handle, [
                    $property->id,
                    $property->title,
                    $property->category,
                    $property->price,
                    $property->bua,
                    $property->sbeds,
                    $property->status,
                    $property->location,
                    $property->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Get applied filters summary from request.
     */
    private function getAppliedFilters(array $validated): array
    {
        $applied = [];

        foreach ($validated as $key => $value) {
            if (!empty($value) && $key !== 'q' && $key !== 'page' && $key !== 'per_page') {
                $applied[$key] = $value;
            }
        }

        return $applied;
    }
}
