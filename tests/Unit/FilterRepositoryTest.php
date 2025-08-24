<?php

namespace Litepie\Repository\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Litepie\Repository\Tests\Models\User;
use Litepie\Repository\Tests\Models\Post;
use Litepie\Repository\Tests\Repositories\UserRepository;
use Litepie\Repository\Tests\TestCase;

class FilterRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->userRepository = new UserRepository();
    }

    protected function createTables(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('posts', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('user_id')->constrained();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    protected function createTestData(): void
    {
        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'inactive'],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'status' => 'active'],
            ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'status' => 'pending'],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }

    public function test_can_filter_users_with_basic_conditions(): void
    {
        $this->createTestData();

        $filters = ['status' => 'active'];
        $users = $this->userRepository->filterUsers($filters);

        $this->assertCount(2, $users);
        
        foreach ($users as $user) {
            $this->assertEquals('active', $user->status);
        }
    }

    public function test_can_filter_with_array_values(): void
    {
        $this->createTestData();

        $filters = ['status' => ['active', 'pending']];
        $users = $this->userRepository->filterUsers($filters);

        $this->assertCount(3, $users);
        
        $statuses = $users->pluck('status')->unique()->toArray();
        $this->assertContains('active', $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertNotContains('inactive', $statuses);
    }

    public function test_can_apply_single_filter_with_operators(): void
    {
        $this->createTestData();

        // Test LIKE operator
        $users = $this->userRepository
            ->applyFilter('name', 'John', 'like')
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);

        // Reset and test starts_with operator
        $users = $this->userRepository
            ->resetQuery()
            ->applyFilter('name', 'Bob', 'starts_with')
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Bob Wilson', $users->first()->name);
    }

    public function test_can_search_across_multiple_columns(): void
    {
        $this->createTestData();

        $users = $this->userRepository->searchUsers('john');

        $this->assertCount(2, $users); // John Doe and Alice Johnson
        
        $names = $users->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Alice Johnson', $names);
    }

    public function test_can_filter_by_date_range(): void
    {
        $this->createTestData();

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $users = $this->userRepository->getUsersByDateRange($yesterday, $today);

        $this->assertCount(4, $users); // All users created today
    }

    public function test_can_use_conditional_filters(): void
    {
        $this->createTestData();

        // Test with status condition
        $conditions = ['status' => 'active'];
        $users = $this->userRepository->getConditionalUsers($conditions);

        $this->assertCount(2, $users);

        // Test with search condition
        $conditions = ['search' => 'john'];
        $users = $this->userRepository->getConditionalUsers($conditions);

        $this->assertCount(2, $users);

        // Test with empty conditions
        $conditions = [];
        $users = $this->userRepository->getConditionalUsers($conditions);

        $this->assertCount(4, $users); // All users
    }

    public function test_can_apply_advanced_filters(): void
    {
        $this->createTestData();

        $filters = [
            [
                'field' => 'status',
                'value' => ['active', 'pending'],
                'operator' => 'in',
            ],
            [
                'field' => 'name',
                'value' => 'John',
                'operator' => 'like',
            ],
        ];

        $users = $this->userRepository->advancedUserFilter($filters);

        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    public function test_can_filter_with_or_conditions(): void
    {
        $this->createTestData();

        $filters = [
            'name' => 'John Doe',
            'email' => 'jane@example.com',
        ];

        $users = $this->userRepository->orFilter($filters)->get();

        $this->assertCount(2, $users);
        
        $names = $users->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    public function test_can_filter_by_relationship(): void
    {
        $this->createTestData();

        $user = User::first();
        Post::create([
            'title' => 'Test Post',
            'content' => 'Test content',
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        $postFilters = ['status' => 'published'];
        $users = $this->userRepository->filterByPosts($postFilters);

        $this->assertCount(1, $users);
        $this->assertEquals($user->id, $users->first()->id);
    }

    public function test_can_filter_by_relationship_count(): void
    {
        $this->createTestData();

        $user = User::first();
        Post::create([
            'title' => 'Test Post 1',
            'content' => 'Test content 1',
            'user_id' => $user->id,
        ]);
        Post::create([
            'title' => 'Test Post 2',
            'content' => 'Test content 2',
            'user_id' => $user->id,
        ]);

        $users = $this->userRepository
            ->filterByRelationCount('posts', '>=', 2)
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals($user->id, $users->first()->id);
    }

    public function test_can_use_nested_filters(): void
    {
        $this->createTestData();

        $users = $this->userRepository
            ->nestedFilter(function ($query) {
                $query->where('status', 'active')
                      ->orWhere('status', 'pending');
            })
            ->where('name', 'LIKE', '%John%')
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    public function test_can_get_filtered_results_with_count(): void
    {
        $this->createTestData();

        $filters = ['status' => 'active'];
        $result = $this->userRepository->getFilteredUsersWithStats($filters);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('filtered', $result);
        $this->assertArrayHasKey('filters_applied', $result);

        $this->assertEquals(4, $result['total']);
        $this->assertEquals(2, $result['filtered']);
        $this->assertTrue($result['filters_applied']);
        $this->assertCount(2, $result['data']);
    }

    public function test_can_use_dynamic_filters(): void
    {
        $this->createTestData();

        $requestData = [
            'name' => 'john',
            'status' => 'active,pending',
            'created_from' => now()->subDay()->toDateString(),
        ];

        $result = $this->userRepository->dynamicUserFilter($requestData);

        $this->assertGreaterThan(0, $result->total());
    }

    public function test_can_transform_filter_values(): void
    {
        $this->createTestData();

        $repository = $this->userRepository;
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('transformFilterValue');
        $method->setAccessible(true);

        // Test array transformation
        $result = $method->invoke($repository, 'active,pending', 'array');
        $this->assertEquals(['active', 'pending'], $result);

        // Test int transformation
        $result = $method->invoke($repository, '123', 'int');
        $this->assertEquals(123, $result);

        // Test bool transformation
        $result = $method->invoke($repository, 'true', 'bool');
        $this->assertTrue($result);

        // Test lowercase transformation
        $result = $method->invoke($repository, 'HELLO', 'lowercase');
        $this->assertEquals('hello', $result);
    }

    public function test_can_filter_and_paginate(): void
    {
        $this->createTestData();

        $filters = ['status' => 'active'];
        $result = $this->userRepository->filterAndPaginate($filters, 1);

        $this->assertEquals(1, $result->perPage());
        $this->assertEquals(2, $result->total());
        $this->assertEquals(2, $result->lastPage());
    }

    public function test_ignores_empty_filter_values(): void
    {
        $this->createTestData();

        $filters = [
            'status' => 'active',
            'name' => '',
            'email' => null,
            'description' => [],
        ];

        $users = $this->userRepository->filterUsers($filters);

        // Should only filter by status, ignoring empty values
        $this->assertCount(2, $users);
        
        foreach ($users as $user) {
            $this->assertEquals('active', $user->status);
        }
    }
}
