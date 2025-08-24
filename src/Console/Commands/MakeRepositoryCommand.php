<?php

namespace Litepie\Repository\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:repository {name : The name of the repository}
                            {--model= : The name of the model}
                            {--no-interface : Do not create repository interface}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new repository class and interface';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $model = $this->option('model');
        $createInterface = !$this->option('no-interface');

        // Create repository
        $this->createRepository($name, $model, $createInterface);

        // Create interface if requested
        if ($createInterface) {
            $this->createInterface($name);
        }

        $this->info('Repository created successfully!');

        return self::SUCCESS;
    }

    /**
     * Create the repository class.
     */
    protected function createRepository(string $name, ?string $model, bool $hasInterface): void
    {
        $repositoryName = Str::studly($name);
        $repositoryPath = $this->getRepositoryPath($repositoryName);
        $repositoryNamespace = $this->getRepositoryNamespace($repositoryName);

        // Determine model name
        $modelName = $model ?: Str::before($repositoryName, 'Repository');
        $modelClass = config('repository.model_namespace', 'App\\Models') . '\\' . $modelName;

        // Generate repository content
        $stub = $this->getRepositoryStub();
        $content = $this->replacePlaceholders($stub, [
            'RepositoryNamespace' => $repositoryNamespace,
            'RepositoryClass' => $repositoryName,
            'ModelClass' => $modelClass,
            'ModelName' => $modelName,
            'InterfaceNamespace' => $hasInterface ? $this->getInterfaceNamespace($repositoryName) : null,
            'InterfaceName' => $hasInterface ? $repositoryName . 'Interface' : null,
        ]);

        $this->makeDirectory($repositoryPath);
        $this->files->put($repositoryPath, $content);

        $this->line("<info>Created Repository:</info> {$repositoryPath}");
    }

    /**
     * Create the repository interface.
     */
    protected function createInterface(string $name): void
    {
        $interfaceName = Str::studly($name) . 'Interface';
        $interfacePath = $this->getInterfacePath($interfaceName);
        $interfaceNamespace = $this->getInterfaceNamespace($interfaceName);

        $stub = $this->getInterfaceStub();
        $content = $this->replacePlaceholders($stub, [
            'InterfaceNamespace' => $interfaceNamespace,
            'InterfaceName' => $interfaceName,
        ]);

        $this->makeDirectory($interfacePath);
        $this->files->put($interfacePath, $content);

        $this->line("<info>Created Interface:</info> {$interfacePath}");
    }

    /**
     * Get the repository stub content.
     */
    protected function getRepositoryStub(): string
    {
        $stubPath = __DIR__ . '/../../../stubs/repository.stub';
        
        if (!$this->files->exists($stubPath)) {
            return $this->getDefaultRepositoryStub();
        }

        return $this->files->get($stubPath);
    }

    /**
     * Get the interface stub content.
     */
    protected function getInterfaceStub(): string
    {
        $stubPath = __DIR__ . '/../../../stubs/interface.stub';
        
        if (!$this->files->exists($stubPath)) {
            return $this->getDefaultInterfaceStub();
        }

        return $this->files->get($stubPath);
    }

    /**
     * Get the default repository stub.
     */
    protected function getDefaultRepositoryStub(): string
    {
        return '<?php

namespace {{ RepositoryNamespace }};

use {{ ModelClass }};
{{ InterfaceImport }}
use Litepie\Repository\BaseRepository;

class {{ RepositoryClass }} extends BaseRepository{{ InterfaceImplements }}
{
    /**
     * Specify the model class name.
     */
    public function model(): string
    {
        return {{ ModelName }}::class;
    }
}';
    }

    /**
     * Get the default interface stub.
     */
    protected function getDefaultInterfaceStub(): string
    {
        return '<?php

namespace {{ InterfaceNamespace }};

use Litepie\Repository\Contracts\RepositoryInterface;

interface {{ InterfaceName }} extends RepositoryInterface
{
    //
}';
    }

    /**
     * Replace placeholders in stub.
     */
    protected function replacePlaceholders(string $stub, array $replacements): string
    {
        $content = $stub;

        foreach ($replacements as $placeholder => $value) {
            $content = str_replace('{{ ' . $placeholder . ' }}', $value, $content);
        }

        // Handle conditional imports and implements
        if (isset($replacements['InterfaceNamespace']) && $replacements['InterfaceNamespace']) {
            $content = str_replace(
                '{{ InterfaceImport }}',
                'use ' . $replacements['InterfaceNamespace'] . '\\' . $replacements['InterfaceName'] . ';',
                $content
            );
            $content = str_replace(
                '{{ InterfaceImplements }}',
                ' implements ' . $replacements['InterfaceName'],
                $content
            );
        } else {
            $content = str_replace('{{ InterfaceImport }}', '', $content);
            $content = str_replace('{{ InterfaceImplements }}', '', $content);
        }

        return $content;
    }

    /**
     * Get the repository path.
     */
    protected function getRepositoryPath(string $name): string
    {
        $path = config('repository.repository_path', 'app/Repositories');
        return base_path($path . '/' . $name . '.php');
    }

    /**
     * Get the interface path.
     */
    protected function getInterfacePath(string $name): string
    {
        $path = config('repository.interface_path', 'app/Repositories/Contracts');
        return base_path($path . '/' . $name . '.php');
    }

    /**
     * Get the repository namespace.
     */
    protected function getRepositoryNamespace(string $name): string
    {
        return config('repository.repository_namespace', 'App\\Repositories');
    }

    /**
     * Get the interface namespace.
     */
    protected function getInterfaceNamespace(string $name): string
    {
        return config('repository.interface_namespace', 'App\\Repositories\\Contracts');
    }

    /**
     * Build the directory for the class if necessary.
     */
    protected function makeDirectory(string $path): void
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true);
        }
    }
}
