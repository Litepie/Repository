<?php

namespace App\Repositories;

use App\Models\Property;
use Litepie\Repository\BaseRepository;

/**
 * Example Property Repository showing query string filter parsing
 */
class PropertyRepository extends BaseRepository
{
    /**
     * Specify the model class name.
     */
    public function model(): string
    {
        return Property::class;
    }

    /**
     * Search properties with complex filters from query string.
     * 
     * Example filter string:
     * "category:IN(Apartment,Bungalow);leads:IN(1,3);manager_of:IN(1449282);status:IN(Published);bua:BETWEEN(5000,3000);rental_period:IN(monthly);sbeds:IN(1,2,3);portals:IN(bayut)"
     */
    public function searchWithFilters(string $filterString, array $options = [])
    {
        // Define allowed fields for security
        $allowedFields = [
            'category',
            'leads', 
            'manager_of',
            'status',
            'bua', // Built-up area
            'rental_period',
            'sbeds', // Studio/bedrooms
            'portals',
            'price',
            'location',
            'property_type',
            'furnished',
            'parking',
            'bathrooms',
            'balcony',
            'created_at',
            'updated_at',
        ];

        // Parse and apply filters
        $this->parseQueryFilters($filterString, $allowedFields);

        // Apply additional options
        if (isset($options['sort'])) {
            $this->applySorting($options['sort']);
        }

        if (isset($options['with'])) {
            $this->with($options['with']);
        }

        return $this;
    }

    /**
     * Get properties with filters from request parameters.
     */
    public function getPropertiesFromRequest(array $requestData)
    {
        $allowedFields = [
            'category', 'status', 'bua', 'rental_period', 'sbeds', 
            'portals', 'price', 'location', 'property_type', 'furnished'
        ];

        // Parse filters from request
        $this->parseRequestFilters($requestData, $allowedFields);

        // Apply default ordering
        $this->orderBy('created_at', 'desc');

        return $this;
    }

    /**
     * Advanced property search with multiple filter formats.
     */
    public function advancedSearch(array $params)
    {
        $allowedFields = [
            'category', 'status', 'bua', 'rental_period', 'sbeds', 
            'portals', 'price', 'location', 'property_type', 'furnished',
            'manager_of', 'leads', 'bathrooms', 'parking', 'balcony'
        ];

        // Handle complex filter string
        if (isset($params['filters']) && is_string($params['filters'])) {
            $this->parseQueryFilters($params['filters'], $allowedFields);
        }

        // Handle individual filter parameters
        if (isset($params['category']) && !empty($params['category'])) {
            if (is_array($params['category'])) {
                $this->whereIn('category', $params['category']);
            } else {
                $this->where('category', $params['category']);
            }
        }

        // Price range handling
        if (isset($params['min_price']) && isset($params['max_price'])) {
            $this->whereBetween('price', [$params['min_price'], $params['max_price']]);
        } elseif (isset($params['min_price'])) {
            $this->where('price', '>=', $params['min_price']);
        } elseif (isset($params['max_price'])) {
            $this->where('price', '<=', $params['max_price']);
        }

        // Area range handling
        if (isset($params['min_area']) && isset($params['max_area'])) {
            $this->whereBetween('bua', [$params['min_area'], $params['max_area']]);
        }

        // Location search
        if (isset($params['location']) && !empty($params['location'])) {
            $this->where('location', 'LIKE', '%' . $params['location'] . '%');
        }

        // Date range filters
        if (isset($params['date_from'])) {
            $this->whereDate('created_at', '>=', $params['date_from']);
        }
        if (isset($params['date_to'])) {
            $this->whereDate('created_at', '<=', $params['date_to']);
        }

        return $this;
    }

    /**
     * Apply sorting based on sort parameter.
     */
    protected function applySorting(string $sortParam): void
    {
        $sortOptions = [
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'area_asc' => ['bua', 'asc'],
            'area_desc' => ['bua', 'desc'],
            'date_asc' => ['created_at', 'asc'],
            'date_desc' => ['created_at', 'desc'],
            'relevance' => ['score', 'desc'], // Custom relevance scoring
        ];

        if (isset($sortOptions[$sortParam])) {
            [$column, $direction] = $sortOptions[$sortParam];
            $this->orderBy($column, $direction);
        }
    }

    /**
     * Build filter URL for frontend.
     */
    public function buildFilterUrl(array $filters): string
    {
        $filterConditions = [];

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $filterConditions[$field] = [
                    'operator' => 'IN',
                    'values' => $value
                ];
            } elseif (is_string($value) && strpos($value, '-') !== false) {
                // Handle range values like "1000-5000"
                $parts = explode('-', $value);
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $filterConditions[$field] = [
                        'operator' => 'BETWEEN',
                        'values' => [(int)$parts[0], (int)$parts[1]]
                    ];
                }
            } else {
                $filterConditions[$field] = [
                    'operator' => 'EQ',
                    'values' => [$value]
                ];
            }
        }

        return static::buildFilterString($filterConditions);
    }

    /**
     * Get filter summary for display.
     */
    public function getFilterSummary(string $filterString): array
    {
        if (empty($filterString)) {
            return [];
        }

        $filters = $this->parseFilterString($filterString);
        $summary = [];

        foreach ($filters as $field => $condition) {
            $operator = $condition['operator'];
            $values = $condition['values'];

            switch ($operator) {
                case 'IN':
                    $summary[$field] = [
                        'label' => ucfirst(str_replace('_', ' ', $field)),
                        'display' => implode(', ', $values),
                        'type' => 'list'
                    ];
                    break;

                case 'BETWEEN':
                    $summary[$field] = [
                        'label' => ucfirst(str_replace('_', ' ', $field)),
                        'display' => $values[0] . ' - ' . $values[1],
                        'type' => 'range'
                    ];
                    break;

                case 'EQ':
                    $summary[$field] = [
                        'label' => ucfirst(str_replace('_', ' ', $field)),
                        'display' => $values[0],
                        'type' => 'single'
                    ];
                    break;

                default:
                    $summary[$field] = [
                        'label' => ucfirst(str_replace('_', ' ', $field)),
                        'display' => $operator . ': ' . implode(', ', $values),
                        'type' => 'complex'
                    ];
            }
        }

        return $summary;
    }

    /**
     * Example method showing real estate specific filters.
     */
    public function searchRealEstate(array $criteria)
    {
        $allowedFields = [
            'property_type', 'status', 'price', 'bedrooms', 'bathrooms',
            'area', 'location', 'amenities', 'furnished', 'parking'
        ];

        // Example filter string for real estate:
        // "property_type:IN(apartment,villa);price:BETWEEN(100000,500000);bedrooms:IN(2,3,4);location:LIKE(dubai);furnished:EQ(yes)"
        
        if (isset($criteria['filter_string'])) {
            $this->parseQueryFilters($criteria['filter_string'], $allowedFields);
        }

        // Handle special real estate filters
        if (isset($criteria['near_metro']) && $criteria['near_metro']) {
            $this->where('metro_distance', '<=', 1000); // Within 1km of metro
        }

        if (isset($criteria['has_pool']) && $criteria['has_pool']) {
            $this->whereJsonContains('amenities', 'pool');
        }

        if (isset($criteria['pet_friendly']) && $criteria['pet_friendly']) {
            $this->where('pet_friendly', true);
        }

        return $this;
    }
}
