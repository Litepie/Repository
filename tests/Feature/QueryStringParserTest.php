<?php

namespace Litepie\Repository\Tests\Feature;

use Litepie\Repository\Tests\TestCase;
use Litepie\Repository\Tests\Models\TestUser;
use Litepie\Repository\Tests\Repositories\TestUserRepository;

class QueryStringParserTest extends TestCase
{
    protected TestUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TestUserRepository();
    }

    /** @test */
    public function it_can_parse_simple_equality_filter()
    {
        TestUser::factory()->create(['status' => 'active']);
        TestUser::factory()->create(['status' => 'inactive']);

        $filterString = 'status:EQ(active)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['status'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
    }

    /** @test */
    public function it_can_parse_in_operator()
    {
        TestUser::factory()->create(['role' => 'admin']);
        TestUser::factory()->create(['role' => 'moderator']);
        TestUser::factory()->create(['role' => 'user']);

        $filterString = 'role:IN(admin,moderator)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['role'])
            ->get();

        $this->assertCount(2, $results);
        $this->assertContains('admin', $results->pluck('role')->toArray());
        $this->assertContains('moderator', $results->pluck('role')->toArray());
    }

    /** @test */
    public function it_can_parse_between_operator()
    {
        TestUser::factory()->create(['age' => 20]);
        TestUser::factory()->create(['age' => 30]);
        TestUser::factory()->create(['age' => 40]);

        $filterString = 'age:BETWEEN(25,35)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['age'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(30, $results->first()->age);
    }

    /** @test */
    public function it_can_parse_multiple_filters()
    {
        TestUser::factory()->create(['status' => 'active', 'role' => 'admin', 'age' => 30]);
        TestUser::factory()->create(['status' => 'active', 'role' => 'user', 'age' => 25]);
        TestUser::factory()->create(['status' => 'inactive', 'role' => 'admin', 'age' => 35]);

        $filterString = 'status:EQ(active);role:EQ(admin);age:GTE(25)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['status', 'role', 'age'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
        $this->assertEquals('admin', $results->first()->role);
    }

    /** @test */
    public function it_can_parse_like_operator()
    {
        TestUser::factory()->create(['name' => 'John Doe']);
        TestUser::factory()->create(['name' => 'Jane Smith']);
        TestUser::factory()->create(['name' => 'Johnny Cash']);

        $filterString = 'name:LIKE(John)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['name'])
            ->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->pluck('name')->contains('John Doe'));
        $this->assertTrue($results->pluck('name')->contains('Johnny Cash'));
    }

    /** @test */
    public function it_can_parse_date_operators()
    {
        TestUser::factory()->create(['created_at' => '2024-01-15']);
        TestUser::factory()->create(['created_at' => '2024-06-15']);
        TestUser::factory()->create(['created_at' => '2024-12-15']);

        $filterString = 'created_at:DATE_BETWEEN(2024-01-01,2024-06-30)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['created_at'])
            ->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_respects_allowed_fields_security()
    {
        TestUser::factory()->create(['status' => 'active', 'role' => 'admin']);
        
        // Try to filter by role but don't allow it
        $filterString = 'status:EQ(active);role:EQ(admin)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['status']) // Only allow status
            ->get();

        // Should filter by status but ignore role filter
        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_can_validate_filter_strings()
    {
        // Valid filter string
        $validFilter = 'status:EQ(active);role:IN(admin,user)';
        $validation = $this->repository::validateFilterString($validFilter);
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);

        // Invalid filter string
        $invalidFilter = 'status:INVALID_OP(active);role:IN(';
        $validation = $this->repository::validateFilterString($invalidFilter);
        
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /** @test */
    public function it_can_build_filter_strings()
    {
        $filters = [
            'status' => [
                'operator' => 'EQ',
                'values' => ['active']
            ],
            'role' => [
                'operator' => 'IN',
                'values' => ['admin', 'moderator']
            ],
            'age' => [
                'operator' => 'BETWEEN',
                'values' => [25, 35]
            ]
        ];

        $filterString = $this->repository::buildFilterString($filters);
        
        $this->assertStringContains('status:EQ(active)', $filterString);
        $this->assertStringContains('role:IN(admin,moderator)', $filterString);
        $this->assertStringContains('age:BETWEEN(25,35)', $filterString);
    }

    /** @test */
    public function it_can_parse_quoted_values()
    {
        TestUser::factory()->create(['name' => 'Smith, John']);
        TestUser::factory()->create(['name' => 'Doe, Jane']);

        // Values with commas should be quoted
        $filterString = 'name:EQ("Smith, John")';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['name'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Smith, John', $results->first()->name);
    }

    /** @test */
    public function it_can_parse_null_operators()
    {
        TestUser::factory()->create(['email_verified_at' => now()]);
        TestUser::factory()->create(['email_verified_at' => null]);

        $filterString = 'email_verified_at:IS_NULL()';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['email_verified_at'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertNull($results->first()->email_verified_at);
    }

    /** @test */
    public function it_can_parse_request_filters()
    {
        TestUser::factory()->create(['status' => 'active', 'role' => 'admin']);
        TestUser::factory()->create(['status' => 'inactive', 'role' => 'user']);

        $requestData = [
            'filters' => 'status:EQ(active)',
            'filter_role' => 'admin',
        ];
        
        $results = $this->repository
            ->parseRequestFilters($requestData, ['status', 'role'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
        $this->assertEquals('admin', $results->first()->role);
    }

    /** @test */
    public function it_handles_complex_real_estate_filters()
    {
        // Create test properties (using User model for simplicity)
        TestUser::factory()->create([
            'name' => 'Apartment',
            'email' => '1', // leads
            'age' => 1500, // bua
            'status' => 'Published'
        ]);
        
        TestUser::factory()->create([
            'name' => 'Villa',
            'email' => '2',
            'age' => 2500,
            'status' => 'Draft'
        ]);

        // Real estate filter example
        $filterString = 'name:IN(Apartment,Bungalow);email:IN(1,3);age:BETWEEN(1000,2000);status:EQ(Published)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['name', 'email', 'age', 'status'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Apartment', $results->first()->name);
    }

    /** @test */
    public function it_can_get_available_operators()
    {
        $operators = $this->repository::getAvailableOperators();
        
        $this->assertIsArray($operators);
        $this->assertArrayHasKey('EQ', $operators);
        $this->assertArrayHasKey('IN', $operators);
        $this->assertArrayHasKey('BETWEEN', $operators);
        $this->assertArrayHasKey('LIKE', $operators);
        $this->assertArrayHasKey('IS_NULL', $operators);
        $this->assertArrayHasKey('DATE_BETWEEN', $operators);
    }

    /** @test */
    public function it_handles_type_conversion()
    {
        TestUser::factory()->create(['age' => 25]);
        TestUser::factory()->create(['age' => 30]);

        // Numbers should be converted properly
        $filterString = 'age:GT(27)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['age'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(30, $results->first()->age);
    }

    /** @test */
    public function it_handles_boolean_values()
    {
        TestUser::factory()->create(['status' => 'active']); // Treating as boolean true
        TestUser::factory()->create(['status' => 'inactive']); // Treating as boolean false

        $filterString = 'status:EQ(active)';
        
        $results = $this->repository
            ->parseQueryFilters($filterString, ['status'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results->first()->status);
    }
}
