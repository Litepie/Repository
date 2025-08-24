<?php

namespace Litepie\Repository\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Litepie\Repository\Tests\Models\User;
use Litepie\Repository\Tests\Repositories\UserRepository;
use Litepie\Repository\Tests\TestCase;

class BaseRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();
        $this->repository = new UserRepository();
    }

    protected function createUsersTable(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function test_can_create_user(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ];

        $user = $this->repository->create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function test_can_find_user(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $foundUser = $this->repository->find($user->id);

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($user->id, $foundUser->id);
    }

    public function test_can_update_user(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $updatedUser = $this->repository->update($user->id, ['name' => 'John Smith']);

        $this->assertEquals('John Smith', $updatedUser->name);
    }

    public function test_can_delete_user(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $result = $this->repository->delete($user->id);

        $this->assertTrue($result);
        $this->assertNull($this->repository->find($user->id));
    }

    public function test_can_get_all_users(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        $users = $this->repository->all();

        $this->assertCount(2, $users);
    }

    public function test_can_paginate_users(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
            ]);
        }

        $paginatedUsers = $this->repository->paginate(10);

        $this->assertEquals(10, $paginatedUsers->perPage());
        $this->assertEquals(20, $paginatedUsers->total());
        $this->assertEquals(2, $paginatedUsers->lastPage());
    }

    public function test_can_find_with_where_conditions(): void
    {
        User::create(['name' => 'Active User', 'email' => 'active@example.com', 'status' => 'active']);
        User::create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'status' => 'inactive']);

        $activeUsers = $this->repository->findWhere([
            ['status', '=', 'active']
        ]);

        $this->assertCount(1, $activeUsers);
        $this->assertEquals('active', $activeUsers->first()->status);
    }

    public function test_can_chain_query_methods(): void
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active']);
        User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'status' => 'inactive']);
        User::create(['name' => 'Bob Smith', 'email' => 'bob@example.com', 'status' => 'active']);

        $users = $this->repository
            ->where('status', 'active')
            ->orderBy('name', 'desc')
            ->get();

        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }

    public function test_custom_repository_methods(): void
    {
        User::create(['name' => 'Active User', 'email' => 'active@example.com', 'status' => 'active']);
        User::create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'status' => 'inactive']);

        $activeUsers = $this->repository->findActiveUsers();
        $user = $this->repository->findByEmail('active@example.com');

        $this->assertCount(1, $activeUsers);
        $this->assertEquals('active', $activeUsers->first()->status);
        $this->assertEquals('active@example.com', $user->email);
    }
}
