<?php

namespace DarkGhostHunter\Laraconfig\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * @internal
 */
class PublishCommand extends Command
{
    protected const STUB_PATH = __DIR__ . '/../../../stubs/users.php';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'settings:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publishes a sample file manifest with settings.';

    /**
     * Migrator constructor.
     *
     * @return void
     */
    public function __construct(protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $path = $this->getLaravel()->basePath('settings/users.php');

        // Add the manifest if it doesn't exists, or if the user confirms the replace.
        if ($this->filesystem->missing($path) || $this->confirm('A manifest file already exists. Overwrite?')) {
            $this->filesystem->ensureDirectoryExists($this->laravel->basePath('settings'));
            $this->filesystem->copy(static::STUB_PATH, $path);

            $this->info("Manifest published. Check it at: $path");
        }
    }
}
