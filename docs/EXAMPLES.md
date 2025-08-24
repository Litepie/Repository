# Repository Pattern Examples

This document provides comprehensive examples of using the Litepie Repository package.

## Table of Contents

1. [Basic Usage](#basic-usage)
2. [CRUD Operations](#crud-operations)
3. [Query Building](#query-building)
4. [Joins](#joins)
5. [Filtering](#filtering)
6. [Searching](#searching)
7. [Pagination](#pagination)
8. [Relationships](#relationships)
9. [Caching](#caching)
10. [Advanced Patterns](#advanced-patterns)

## Basic Usage

### Creating a Repository

First, create a repository for your model:

```bash
php artisan make:repository User
```

This generates:
- `app/Repositories/UserRepository.php`
- `app/Repositories/Contracts/UserRepositoryInterface.php`

### Binding in Service Provider

```php
// app/Providers/AppServiceProvider.php
use App\Repositories\UserRepository;
use App\Repositories\Contracts\UserRepositoryInterface;

public function register()
{
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
}
```

### Using in Controller

```php
use App\Repositories\Contracts\UserRepositoryInterface;

class UserController extends Controller
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function index()
    {
        $users = $this->userRepository->paginate(15);
        return view('users.index', compact('users'));
    }
}
```

## CRUD Operations

### Create Operations

```php
// Simple create
$user = $userRepository->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password')
]);

// Create or update
$user = $userRepository->updateOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Smith', 'last_login' => now()]
);

// Bulk insert
$userRepository->insert([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
    ['name' => 'User 3', 'email' => 'user3@example.com'],
]);
```

### Read Operations

```php
// Find by ID
$user = $userRepository->find(1);

// Find with exception if not found
$user = $userRepository->findOrFail(1);

// Find by specific field
$user = $userRepository->findBy('email', 'john@example.com');

// Find multiple records
$users = $userRepository->findWhereIn('id', [1, 2, 3]);

// Get all records
$users = $userRepository->all();

// Get all with columns
$users = $userRepository->all(['id', 'name', 'email']);
```

### Update Operations

```php
// Update by ID
$userRepository->update(1, ['name' => 'Jane Doe']);

// Update multiple records
$userRepository->updateWhere(
    ['status' => 'inactive'],
    ['status' => 'active']
);

// Increment/Decrement
$userRepository->increment(1, 'login_count');
$userRepository->decrement(1, 'credits', 5);
```

### Delete Operations

```php
// Delete by ID
$userRepository->delete(1);

// Delete multiple
$userRepository->deleteWhere(['status' => 'inactive']);

// Soft delete
$userRepository->destroy(1);

// Force delete
$userRepository->forceDelete(1);

// Restore soft deleted
$userRepository->restore(1);
```

## Query Building

### Basic Queries

```php
// Where conditions
$users = $userRepository
    ->where('status', 'active')
    ->where('created_at', '>', now()->subDays(30))
    ->get();

// Or where
$users = $userRepository
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Where in
$users = $userRepository
    ->whereIn('role', ['admin', 'moderator', 'editor'])
    ->get();

// Where not in
$users = $userRepository
    ->whereNotIn('status', ['banned', 'suspended'])
    ->get();

// Where null
$users = $userRepository
    ->whereNull('email_verified_at')
    ->get();

// Where between
$users = $userRepository
    ->whereBetween('created_at', [now()->subMonth(), now()])
    ->get();
```

### Complex Queries

```php
// Group conditions
$users = $userRepository
    ->where(function($query) {
        $query->where('role', 'admin')
              ->orWhere('role', 'moderator');
    })
    ->where('status', 'active')
    ->get();

// Having clauses
$users = $userRepository
    ->selectRaw('role, COUNT(*) as count')
    ->groupBy('role')
    ->having('count', '>', 10)
    ->get();

// Raw queries
$users = $userRepository
    ->whereRaw('DATE(created_at) = ?', [today()])
    ->orderByRaw('RAND()')
    ->limit(5)
    ->get();
```

## Joins

### Basic Joins

```php
// Inner join
$posts = $postRepository
    ->select(['posts.*', 'users.name as author'])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->where('posts.status', 'published')
    ->get();

// Left join
$posts = $postRepository
    ->select(['posts.*', 'categories.name as category'])
    ->leftJoin('categories', 'posts.category_id', '=', 'categories.id')
    ->get();

// Right join
$users = $userRepository
    ->select(['users.*', 'profiles.bio'])
    ->rightJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

### Complex Joins

```php
// Multiple joins
$posts = $postRepository
    ->select([
        'posts.*',
        'users.name as author',
        'categories.name as category',
        'tags.name as tag'
    ])
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->leftJoin('categories', 'posts.category_id', '=', 'categories.id')
    ->leftJoin('post_tags', 'posts.id', '=', 'post_tags.post_id')
    ->leftJoin('tags', 'post_tags.tag_id', '=', 'tags.id')
    ->where('posts.status', 'published')
    ->orderBy('posts.created_at', 'desc')
    ->get();

// Join with conditions
$posts = $postRepository
    ->select(['posts.*', 'users.name as author'])
    ->join('users', function($join) {
        $join->on('posts.user_id', '=', 'users.id')
             ->where('users.status', 'active');
    })
    ->get();

// Cross join
$combinations = $userRepository
    ->select(['users.name', 'roles.name as role'])
    ->crossJoin('roles')
    ->get();
```

### Join with Aggregates

```php
// Count related records
$users = $userRepository
    ->select(['users.*'])
    ->selectRaw('COUNT(posts.id) as posts_count')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->groupBy('users.id')
    ->having('posts_count', '>', 5)
    ->get();

// Sum related values
$users = $userRepository
    ->select(['users.*'])
    ->selectRaw('SUM(orders.total) as total_spent')
    ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
    ->groupBy('users.id')
    ->orderByDesc('total_spent')
    ->get();
```

## Filtering

### Basic Filtering

```php
// Simple filters
$users = $userRepository->filter([
    'status' => 'active',
    'role' => 'admin'
])->get();

// Array values (WHERE IN)
$users = $userRepository->filter([
    'role' => ['admin', 'moderator']
])->get();

// Null values
$users = $userRepository->filter([
    'email_verified_at' => null
])->get();
```

### Advanced Filtering

```php
// Custom operators
$users = $userRepository->filterAdvanced([
    'created_at' => ['>', now()->subDays(30)],
    'posts_count' => ['>=', 10],
    'name' => ['like', '%john%']
])->get();

// Date ranges
$users = $userRepository
    ->dateRange('created_at', '2024-01-01', '2024-12-31')
    ->get();

// Numeric ranges
$products = $productRepository
    ->numericRange('price', 100, 500)
    ->get();
```

### Conditional Filtering

```php
// When condition
$users = $userRepository
    ->when($request->has('status'), function($query) use ($request) {
        return $query->where('status', $request->status);
    })
    ->when($request->filled('search'), function($query) use ($request) {
        return $query->search($request->search, ['name', 'email']);
    })
    ->get();

// Unless condition
$users = $userRepository
    ->unless($request->show_all, function($query) {
        return $query->where('status', 'active');
    })
    ->get();
```

### Request-Based Filtering

```php
// Filter from request
$users = $userRepository
    ->filterFromRequest($request->all(), ['name', 'email', 'status'])
    ->sortFromRequest($request->all(), ['name', 'created_at', 'updated_at'])
    ->paginate(15);

// With validation
$allowedFilters = ['status', 'role', 'created_at'];
$allowedSorts = ['name', 'email', 'created_at'];

$users = $userRepository
    ->filterFromRequest($request->validated(), $allowedFilters)
    ->sortFromRequest($request->validated(), $allowedSorts)
    ->paginate(15);
```

## Searching

### Basic Search

```php
// Search in multiple fields
$users = $userRepository
    ->search('john', ['name', 'email'])
    ->get();

// Search with ranking
$users = $userRepository
    ->searchWithRanking('developer', [
        'name' => 3,        // Highest priority
        'bio' => 2,         // Medium priority
        'skills' => 1       // Lower priority
    ])
    ->get();
```

### Advanced Search

```php
// Full-text search
$posts = $postRepository
    ->fullTextSearch('laravel tutorial', ['title', 'content'])
    ->where('status', 'published')
    ->get();

// Search with filters
$users = $userRepository
    ->search($request->q, ['name', 'email'])
    ->filter(['status' => 'active'])
    ->orderBy('relevance', 'desc')
    ->paginate(20);
```

## Pagination

### Simple Pagination

```php
// Default pagination
$users = $userRepository->paginate(15);

// Custom pagination
$users = $userRepository
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Simple pagination (next/previous only)
$users = $userRepository->simplePaginate(15);
```

### Custom Pagination

```php
// With custom page name
$users = $userRepository->paginate(15, ['*'], 'users_page');

// With appended query parameters
$users = $userRepository
    ->filter($request->all())
    ->paginate(15)
    ->appends($request->query());
```

## Relationships

### Eager Loading

```php
// Load relationships
$users = $userRepository->with(['posts', 'profile'])->get();

// Conditional loading
$users = $userRepository
    ->with(['posts' => function($query) {
        $query->where('status', 'published');
    }])
    ->get();

// Count relationships
$users = $userRepository->withCount(['posts', 'comments'])->get();
```

### Lazy Loading

```php
// Load after retrieval
$user = $userRepository->find(1);
$user->load(['posts.comments', 'profile']);

// Load count
$user->loadCount(['posts', 'comments']);
```

## Caching

### Basic Caching

```php
// Cache results
$users = $userRepository
    ->cache(3600) // Cache for 1 hour
    ->all();

// Cache with custom key
$users = $userRepository
    ->cache(3600, 'active_users')
    ->where('status', 'active')
    ->get();

// Remember results
$users = $userRepository->remember(60)->all();

// Cache tags
$users = $userRepository
    ->cacheTags(['users', 'active'])
    ->cache(3600)
    ->where('status', 'active')
    ->get();
```

### Cache Management

```php
// Forget cache
$userRepository->forgetCache('active_users');

// Flush all cache
$userRepository->flushCache();

// Refresh cache
$users = $userRepository
    ->refreshCache('active_users')
    ->where('status', 'active')
    ->get();
```

## Advanced Patterns

### Repository Inheritance

```php
// Base repository with common methods
abstract class BaseUserRepository extends BaseRepository
{
    protected function getActiveQuery()
    {
        return $this->where('status', 'active');
    }

    public function getActiveUsers()
    {
        return $this->getActiveQuery()->get();
    }
}

// Specific repository
class AdminRepository extends BaseUserRepository
{
    public function model(): string
    {
        return Admin::class;
    }

    public function getActiveAdmins()
    {
        return $this->getActiveQuery()
            ->where('role', 'admin')
            ->get();
    }
}
```

### Repository with Events

```php
class UserRepository extends BaseRepository
{
    public function create(array $data)
    {
        // Before create event
        event(new UserCreating($data));

        $user = parent::create($data);

        // After create event
        event(new UserCreated($user));

        return $user;
    }

    public function update($id, array $data)
    {
        $user = $this->find($id);
        
        event(new UserUpdating($user, $data));
        
        $updated = parent::update($id, $data);
        
        event(new UserUpdated($updated));
        
        return $updated;
    }
}
```

### Repository with Validation

```php
class UserRepository extends BaseRepository
{
    protected array $createRules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8'
    ];

    protected array $updateRules = [
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,{id}',
        'password' => 'sometimes|min:8'
    ];

    public function create(array $data)
    {
        $this->validate($data, $this->createRules);
        return parent::create($data);
    }

    public function update($id, array $data)
    {
        $rules = str_replace('{id}', $id, $this->updateRules);
        $this->validate($data, $rules);
        return parent::update($id, $data);
    }

    protected function validate(array $data, array $rules)
    {
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
```

### Repository with Scopes

```php
class PostRepository extends BaseRepository
{
    public function published()
    {
        return $this->where('status', 'published');
    }

    public function byAuthor($authorId)
    {
        return $this->where('user_id', $authorId);
    }

    public function recent($days = 7)
    {
        return $this->where('created_at', '>=', now()->subDays($days));
    }

    public function popular($views = 1000)
    {
        return $this->where('views', '>=', $views);
    }

    // Chain scopes
    public function getPopularRecentPosts($days = 7, $views = 1000)
    {
        return $this->published()
            ->recent($days)
            ->popular($views)
            ->orderBy('views', 'desc')
            ->get();
    }
}
```

### Repository with Custom Collections

```php
class UserRepository extends BaseRepository
{
    public function newCollection(array $models = [])
    {
        return new UserCollection($models);
    }
}

class UserCollection extends Collection
{
    public function active()
    {
        return $this->filter(function($user) {
            return $user->status === 'active';
        });
    }

    public function admins()
    {
        return $this->filter(function($user) {
            return $user->role === 'admin';
        });
    }

    public function byRole($role)
    {
        return $this->filter(function($user) use ($role) {
            return $user->role === $role;
        });
    }
}
```

This comprehensive guide should help you understand how to use all the features of the Litepie Repository package effectively.
