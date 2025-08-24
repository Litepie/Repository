<?php

namespace Litepie\Repository\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

trait SearchableRepository
{
    /**
     * Search configuration.
     */
    protected array $searchConfig = [
        'engine' => 'database', // database, elasticsearch, algolia
        'fields' => [],
        'weights' => [],
        'fuzzy_threshold' => 0.8,
        'min_score' => 0.1,
        'highlight' => true,
    ];

    /**
     * Elasticsearch configuration.
     */
    protected array $elasticConfig = [
        'host' => 'localhost:9200',
        'index' => null,
        'type' => '_doc',
    ];

    /**
     * Search index.
     */
    protected array $searchIndex = [];

    /**
     * Configure search settings.
     */
    public function configureSearch(array $config): self
    {
        $this->searchConfig = array_merge($this->searchConfig, $config);
        return $this;
    }

    /**
     * Configure Elasticsearch settings.
     */
    public function configureElastic(array $config): self
    {
        $this->elasticConfig = array_merge($this->elasticConfig, $config);
        return $this;
    }

    /**
     * Perform search query.
     */
    public function search(string $query, array $options = []): Collection
    {
        $options = array_merge($this->searchConfig, $options);
        
        $this->fireEvent('search_starting', [
            'query' => $query,
            'engine' => $options['engine'],
            'options' => $options
        ]);

        switch ($options['engine']) {
            case 'elasticsearch':
                $results = $this->elasticSearch($this->buildElasticQuery($query, $options));
                break;
            case 'algolia':
                $results = $this->algoliaSearch($query, $options);
                break;
            default:
                $results = $this->databaseSearch($query, $options);
        }

        $this->fireEvent('search_completed', [
            'query' => $query,
            'results_count' => $results->count(),
            'engine' => $options['engine']
        ]);

        return $results;
    }

    /**
     * Fuzzy search with similarity threshold.
     */
    public function fuzzySearch(string $query, float $threshold = null): Collection
    {
        $threshold = $threshold ?? $this->searchConfig['fuzzy_threshold'];
        
        $this->fireEvent('fuzzy_search_starting', [
            'query' => $query,
            'threshold' => $threshold
        ]);

        $results = collect();
        $searchFields = $this->getSearchFields();

        // Get all records for fuzzy matching
        $records = $this->get();

        foreach ($records as $record) {
            $maxSimilarity = 0;
            $matchedField = null;

            foreach ($searchFields as $field) {
                $value = $this->getFieldValue($record, $field);
                if (!$value) continue;

                $similarity = $this->calculateSimilarity($query, (string) $value);
                
                if ($similarity > $maxSimilarity) {
                    $maxSimilarity = $similarity;
                    $matchedField = $field;
                }
            }

            if ($maxSimilarity >= $threshold) {
                $record->_similarity_score = $maxSimilarity;
                $record->_matched_field = $matchedField;
                $results->push($record);
            }
        }

        // Sort by similarity score
        $results = $results->sortByDesc('_similarity_score');

        $this->fireEvent('fuzzy_search_completed', [
            'query' => $query,
            'threshold' => $threshold,
            'results_count' => $results->count()
        ]);

        return $results;
    }

    /**
     * Full-text search using database.
     */
    public function fullTextSearch(string $query, array $fields = []): Collection
    {
        $fields = $fields ?: $this->getSearchFields();
        
        if (empty($fields)) {
            throw new \InvalidArgumentException('No search fields configured');
        }

        $this->fireEvent('fulltext_search_starting', [
            'query' => $query,
            'fields' => $fields
        ]);

        // Build MATCH AGAINST query for MySQL
        $matchFields = implode(',', $fields);
        $this->query->whereRaw("MATCH({$matchFields}) AGAINST(? IN NATURAL LANGUAGE MODE)", [$query]);
        
        // Add relevance score
        $this->query->selectRaw("*, MATCH({$matchFields}) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance_score", [$query]);
        
        // Order by relevance
        $this->query->orderBy('relevance_score', 'desc');

        $results = $this->get();

        $this->fireEvent('fulltext_search_completed', [
            'query' => $query,
            'fields' => $fields,
            'results_count' => $results->count()
        ]);

        return $results;
    }

    /**
     * Elasticsearch integration.
     */
    public function elasticSearch(array $query): Collection
    {
        $index = $this->elasticConfig['index'] ?? $this->getElasticIndex();
        $url = "http://{$this->elasticConfig['host']}/{$index}/_search";

        $this->fireEvent('elastic_search_starting', [
            'index' => $index,
            'query' => $query
        ]);

        try {
            $response = Http::post($url, $query);
            
            if (!$response->successful()) {
                throw new \Exception("Elasticsearch query failed: " . $response->body());
            }

            $data = $response->json();
            $hits = $data['hits']['hits'] ?? [];

            $results = collect();
            
            foreach ($hits as $hit) {
                $source = $hit['_source'];
                $model = $this->model->newInstance($source);
                $model->_score = $hit['_score'];
                $model->_highlights = $hit['highlight'] ?? [];
                $results->push($model);
            }

            $this->fireEvent('elastic_search_completed', [
                'index' => $index,
                'total_hits' => $data['hits']['total']['value'] ?? 0,
                'results_count' => $results->count()
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->fireEvent('elastic_search_failed', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            throw $e;
        }
    }

    /**
     * Database search implementation.
     */
    protected function databaseSearch(string $query, array $options): Collection
    {
        $fields = $options['fields'] ?: $this->getSearchFields();
        $weights = $options['weights'] ?? [];

        if (empty($fields)) {
            throw new \InvalidArgumentException('No search fields configured');
        }

        $terms = $this->parseSearchQuery($query);
        
        $this->query->where(function ($q) use ($terms, $fields, $weights) {
            foreach ($terms as $term) {
                $q->orWhere(function ($subQ) use ($term, $fields) {
                    foreach ($fields as $field) {
                        $subQ->orWhere($field, 'LIKE', "%{$term}%");
                    }
                });
            }
        });

        // Add relevance scoring if weights are provided
        if (!empty($weights)) {
            $scoreSelect = $this->buildRelevanceScore($terms, $weights);
            $this->query->selectRaw("*, ({$scoreSelect}) as relevance_score");
            $this->query->orderBy('relevance_score', 'desc');
        }

        return $this->get();
    }

    /**
     * Build search index for faster searching.
     */
    public function buildSearchIndex(array $fields = []): bool
    {
        $fields = $fields ?: $this->getSearchFields();
        
        $this->fireEvent('index_building_started', ['fields' => $fields]);

        $this->searchIndex = [];
        $processed = 0;

        $this->query->chunk(1000, function ($records) use ($fields, &$processed) {
            foreach ($records as $record) {
                $indexEntry = [
                    'id' => $record->getKey(),
                    'content' => '',
                    'fields' => []
                ];

                foreach ($fields as $field) {
                    $value = $this->getFieldValue($record, $field);
                    if ($value) {
                        $indexEntry['content'] .= ' ' . $value;
                        $indexEntry['fields'][$field] = $value;
                    }
                }

                $indexEntry['content'] = trim($indexEntry['content']);
                $indexEntry['tokens'] = $this->tokenize($indexEntry['content']);
                
                $this->searchIndex[$record->getKey()] = $indexEntry;
            }
            
            $processed += $records->count();
            
            $this->fireEvent('index_chunk_processed', [
                'chunk_size' => $records->count(),
                'total_processed' => $processed
            ]);
        });

        $this->fireEvent('index_building_completed', [
            'total_records' => count($this->searchIndex),
            'fields' => $fields
        ]);

        return true;
    }

    /**
     * Reindex all data in Elasticsearch.
     */
    public function reindex(): bool
    {
        $index = $this->elasticConfig['index'] ?? $this->getElasticIndex();
        
        $this->fireEvent('reindex_starting', ['index' => $index]);

        try {
            // Delete existing index
            $this->deleteElasticIndex($index);
            
            // Create new index
            $this->createElasticIndex($index);
            
            // Index all documents
            $indexed = 0;
            
            $this->query->chunk(1000, function ($records) use ($index, &$indexed) {
                $body = [];
                
                foreach ($records as $record) {
                    $body[] = [
                        'index' => [
                            '_index' => $index,
                            '_id' => $record->getKey()
                        ]
                    ];
                    $body[] = $record->toArray();
                }
                
                if (!empty($body)) {
                    $this->bulkIndexElastic($body);
                    $indexed += $records->count();
                }
                
                $this->fireEvent('reindex_chunk_processed', [
                    'chunk_size' => $records->count(),
                    'total_indexed' => $indexed
                ]);
            });

            $this->fireEvent('reindex_completed', [
                'index' => $index,
                'total_indexed' => $indexed
            ]);

            return true;
        } catch (\Exception $e) {
            $this->fireEvent('reindex_failed', [
                'error' => $e->getMessage(),
                'index' => $index
            ]);
            
            throw $e;
        }
    }

    /**
     * Get search fields.
     */
    protected function getSearchFields(): array
    {
        if (!empty($this->searchConfig['fields'])) {
            return $this->searchConfig['fields'];
        }

        // Fallback to model fillable fields
        $fillable = $this->model->getFillable();
        
        // Filter to likely text fields
        return array_filter($fillable, function ($field) {
            return in_array($field, ['name', 'title', 'description', 'content', 'summary']);
        });
    }

    /**
     * Get field value from model.
     */
    protected function getFieldValue($model, string $field)
    {
        if (strpos($field, '.') !== false) {
            $parts = explode('.', $field);
            $value = $model;
            
            foreach ($parts as $part) {
                $value = $value->$part ?? null;
                if ($value === null) break;
            }
            
            return $value;
        }

        return $model->$field ?? null;
    }

    /**
     * Calculate string similarity.
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Use Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        
        if ($maxLen === 0) {
            return 0.0;
        }
        
        $distance = levenshtein($str1, $str2);
        
        return 1 - ($distance / $maxLen);
    }

    /**
     * Parse search query into terms.
     */
    protected function parseSearchQuery(string $query): array
    {
        // Remove special characters and split by spaces
        $query = preg_replace('/[^\w\s]/', ' ', $query);
        $terms = array_filter(explode(' ', $query));
        
        // Remove duplicates and short terms
        return array_unique(array_filter($terms, function ($term) {
            return strlen($term) >= 2;
        }));
    }

    /**
     * Build relevance score SQL.
     */
    protected function buildRelevanceScore(array $terms, array $weights): string
    {
        $scores = [];
        
        foreach ($weights as $field => $weight) {
            foreach ($terms as $term) {
                $scores[] = "CASE WHEN {$field} LIKE '%{$term}%' THEN {$weight} ELSE 0 END";
            }
        }
        
        return implode(' + ', $scores);
    }

    /**
     * Tokenize text for indexing.
     */
    protected function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $tokens = array_filter(explode(' ', $text));
        
        return array_unique($tokens);
    }

    /**
     * Build Elasticsearch query.
     */
    protected function buildElasticQuery(string $query, array $options): array
    {
        $fields = $options['fields'] ?: $this->getSearchFields();
        
        return [
            'query' => [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $fields,
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO'
                ]
            ],
            'highlight' => $options['highlight'] ? [
                'fields' => array_fill_keys($fields, new \stdClass())
            ] : null,
            'sort' => [
                '_score' => ['order' => 'desc']
            ]
        ];
    }

    /**
     * Get Elasticsearch index name.
     */
    protected function getElasticIndex(): string
    {
        return strtolower(class_basename($this->model()));
    }

    /**
     * Create Elasticsearch index.
     */
    protected function createElasticIndex(string $index): bool
    {
        $url = "http://{$this->elasticConfig['host']}/{$index}";
        
        $mapping = [
            'mappings' => [
                'properties' => $this->getElasticMapping()
            ]
        ];
        
        $response = Http::put($url, $mapping);
        
        return $response->successful();
    }

    /**
     * Delete Elasticsearch index.
     */
    protected function deleteElasticIndex(string $index): bool
    {
        $url = "http://{$this->elasticConfig['host']}/{$index}";
        
        $response = Http::delete($url);
        
        return $response->successful() || $response->status() === 404;
    }

    /**
     * Bulk index documents in Elasticsearch.
     */
    protected function bulkIndexElastic(array $body): bool
    {
        $url = "http://{$this->elasticConfig['host']}/_bulk";
        
        $bulkBody = '';
        foreach ($body as $line) {
            $bulkBody .= json_encode($line) . "\n";
        }
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-ndjson'
        ])->withBody($bulkBody, 'application/x-ndjson')->post($url);
        
        return $response->successful();
    }

    /**
     * Get Elasticsearch field mapping.
     */
    protected function getElasticMapping(): array
    {
        $fields = $this->getSearchFields();
        $mapping = [];
        
        foreach ($fields as $field) {
            $mapping[$field] = [
                'type' => 'text',
                'analyzer' => 'standard'
            ];
        }
        
        return $mapping;
    }

    /**
     * Algolia search implementation.
     */
    protected function algoliaSearch(string $query, array $options): Collection
    {
        // This would require Algolia SDK integration
        // Placeholder implementation
        throw new \Exception('Algolia search not implemented. Please install and configure Algolia SDK.');
    }
}
