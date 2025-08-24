# Example Implementation

This directory contains examples of how to use the Litepie Repository package.

## User Repository Example

Here's a complete example of implementing a User repository:

### 1. Generate the Repository

```bash
php artisan make:repository UserRepository --model=User
```

### 2. Generated Files

**app/Repositories/UserRepository.php**
```php
<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Litepie\Repository\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * Specify the model class name.
     */
    public function model(): string
    {
        return User::class;
    }

    /**
     * Find active users.
     */
    public function findActiveUsers()
    {
        return $this->where('status', 'active')
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Get users created in the last N days.
     */
    public function getRecentUsers(int $days = 30)
    {
        return $this->where('created_at', '>=', now()->subDays($days))
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    /**
     * Get paginated active users.
     */
    public function getPaginatedActiveUsers(int $perPage = 15)
    {
        return $this->where('status', 'active')
                   ->orderBy('name')
                   ->paginate($perPage);
    }
}
```

**app/Repositories/Contracts/UserRepositoryInterface.php**
```php
<?php

namespace App\Repositories\Contracts;

use Litepie\Repository\Contracts\RepositoryInterface;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findActiveUsers();
    public function findByEmail(string $email);
    public function getRecentUsers(int $days = 30);
    public function getPaginatedActiveUsers(int $perPage = 15);
}
```

### 3. Controller Usage

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $users = $this->userRepository->getPaginatedActiveUsers($perPage);
        
        return view('users.index', compact('users'));
    }

    public function show(int $id)
    {
        $user = $this->userRepository->findOrFail($id);
        return view('users.show', compact('user'));
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->userRepository->create($request->validated());
        
        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User created successfully');
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->userRepository->update($id, $request->validated());
        
        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User updated successfully');
    }

    public function destroy(int $id)
    {
        $this->userRepository->delete($id);
        
        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully');
    }

    public function recent()
    {
        $users = $this->userRepository->getRecentUsers(7); // Last 7 days
        return view('users.recent', compact('users'));
    }

    public function search(Request $request)
    {
        $email = $request->get('email');
        
        if ($email) {
            $user = $this->userRepository->findByEmail($email);
            return view('users.search', compact('user'));
        }

        return view('users.search');
    }
}
```

### 4. Service Binding (Optional)

If you want to manually bind the repository, add this to your `AppServiceProvider`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\UserRepository;
use App\Repositories\Contracts\UserRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot()
    {
        //
    }
}
```

### 5. Advanced Usage

#### With Relationships
```php
// In your repository
public function getUsersWithPosts()
{
    return $this->with(['posts', 'comments'])
               ->where('status', 'active')
               ->get();
}

// Usage
$users = $this->userRepository->getUsersWithPosts();
```

#### Complex Queries
```php
// In your repository
public function searchUsers(array $filters)
{
    $query = $this->resetQuery();

    if (!empty($filters['name'])) {
        $query = $query->where('name', 'LIKE', '%' . $filters['name'] . '%');
    }

    if (!empty($filters['email'])) {
        $query = $query->where('email', 'LIKE', '%' . $filters['email'] . '%');
    }

    if (!empty($filters['status'])) {
        $query = $query->where('status', $filters['status']);
    }

    if (!empty($filters['created_from'])) {
        $query = $query->where('created_at', '>=', $filters['created_from']);
    }

    return $query->orderBy('created_at', 'desc')->get();
}
```

#### Chunked Processing
```php
// Process large datasets efficiently
public function processAllUsers(callable $callback)
{
    return $this->chunk(100, $callback);
}

// Usage
$this->userRepository->processAllUsers(function ($users) {
    foreach ($users as $user) {
        // Process each user
        $this->sendEmailToUser($user);
    }
});
```

This example demonstrates the flexibility and power of the repository pattern implementation.
