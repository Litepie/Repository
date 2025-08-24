<?php

namespace Litepie\Repository\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Litepie\Repository\Tests\Models\User;
use Litepie\Repository\Tests\Models\Post;
use Litepie\Repository\Tests\Models\Comment;
use Litepie\Repository\Tests\Repositories\PostRepository;
use Litepie\Repository\Tests\TestCase;

class JoinRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected PostRepository $postRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->postRepository = new PostRepository();
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
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function ($table) {
            $table->id();
            $table->text('content');
            $table->foreignId('post_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function test_can_join_posts_with_users(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        Post::create([
            'title' => 'Test Post',
            'content' => 'Test content',
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        $posts = $this->postRepository->getPostsWithUsers();

        $this->assertCount(1, $posts);
        $this->assertEquals('Test Post', $posts->first()->title);
        $this->assertEquals('John Doe', $posts->first()->user_name);
        $this->assertEquals('john@example.com', $posts->first()->user_email);
    }

    public function test_can_get_posts_with_comment_count(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $post = Post::create([
            'title' => 'Popular Post',
            'content' => 'Popular content',
            'user_id' => $user->id,
            'status' => 'published',
        ]);

        // Create some comments
        Comment::create([
            'content' => 'Great post!',
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        Comment::create([
            'content' => 'I agree!',
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $posts = $this->postRepository->getPostsWithCommentCount();

        $this->assertCount(1, $posts);
        $this->assertEquals('Popular Post', $posts->first()->title);
        $this->assertEquals('Jane Doe', $posts->first()->author_name);
        $this->assertEquals(2, $posts->first()->comments_count);
    }

    public function test_can_use_complex_join_conditions(): void
    {
        $activeUser = User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'status' => 'active',
        ]);

        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'status' => 'inactive',
        ]);

        Post::create([
            'title' => 'Active User Post',
            'content' => 'Content from active user',
            'user_id' => $activeUser->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        Post::create([
            'title' => 'Inactive User Post',
            'content' => 'Content from inactive user',
            'user_id' => $inactiveUser->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $posts = $this->postRepository->getActivePostsByUser($activeUser->id);

        $this->assertCount(1, $posts);
        $this->assertEquals('Active User Post', $posts->first()->title);
        $this->assertEquals('Active User', $posts->first()->author_name);
    }

    public function test_can_use_date_filters_with_joins(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $oldPost = Post::create([
            'title' => 'Old Post',
            'content' => 'Old content',
            'user_id' => $user->id,
            'status' => 'published',
            'created_at' => now()->subDays(10),
        ]);

        $newPost = Post::create([
            'title' => 'New Post',
            'content' => 'New content',
            'user_id' => $user->id,
            'status' => 'published',
            'created_at' => now()->subDays(2),
        ]);

        $posts = $this->postRepository->getPostsByDateRange(
            now()->subDays(5)->toDateString(),
            now()->toDateString()
        );

        $this->assertCount(1, $posts);
        $this->assertEquals('New Post', $posts->first()->title);
    }

    public function test_can_search_with_multiple_table_conditions(): void
    {
        $user1 = User::create([
            'name' => 'Search User',
            'email' => 'search@example.com',
        ]);

        $user2 = User::create([
            'name' => 'Another User',
            'email' => 'another@example.com',
        ]);

        Post::create([
            'title' => 'Searchable Post',
            'content' => 'This contains search term',
            'user_id' => $user1->id,
            'status' => 'published',
        ]);

        Post::create([
            'title' => 'Other Post',
            'content' => 'This does not contain the term',
            'user_id' => $user2->id,
            'status' => 'published',
        ]);

        Post::create([
            'title' => 'User Match Post',
            'content' => 'Content by Search User',
            'user_id' => $user1->id,
            'status' => 'published',
        ]);

        $posts = $this->postRepository->searchPostsWithDetails('search');

        $this->assertCount(2, $posts);
        
        $titles = $posts->pluck('title')->toArray();
        $this->assertContains('Searchable Post', $titles);
        $this->assertContains('User Match Post', $titles);
    }

    public function test_can_use_advanced_query_methods(): void
    {
        $user = User::create([
            'name' => 'Advanced User',
            'email' => 'advanced@example.com',
        ]);

        Post::create([
            'title' => 'Advanced Post',
            'content' => 'Advanced content',
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Test distinct
        $posts = $this->postRepository
            ->select(['posts.title', 'users.name'])
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->distinct()
            ->get();

        $this->assertCount(1, $posts);

        // Test where null/not null
        $postsWithPublishDate = $this->postRepository
            ->whereNotNull('published_at')
            ->get();

        $this->assertCount(1, $postsWithPublishDate);

        // Test where between
        $recentPosts = $this->postRepository
            ->whereBetween('created_at', [now()->subDays(1), now()])
            ->get();

        $this->assertCount(1, $recentPosts);
    }
}
