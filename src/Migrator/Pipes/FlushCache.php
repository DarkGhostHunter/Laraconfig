<?php

namespace DarkGhostHunter\Laraconfig\Migrator\Pipes;

use Closure;
use DarkGhostHunter\Laraconfig\Migrator\Data;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
class FlushCache
{
    /**
     * FlushCache constructor.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Contracts\Cache\Factory  $factory
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Illuminate\Console\OutputStyle  $output
     */
    public function __construct(
        protected Application $app,
        protected Repository $config,
        protected Factory $factory,
        protected InputInterface $input,
        protected OutputStyle $output,
    ) {
    }

    /**
     * Handles the Settings migration.
     *
     * @param  \DarkGhostHunter\Laraconfig\Migrator\Data  $data
     * @param  \Closure  $next
     *
     * @return mixed
     */
    public function handle(Data $data, Closure $next): mixed
    {
        if ($this->input->getOption('flush-cache')) {
            // If is not using a cache, we will not flush anything and bail.
            if (!$this->config->get('laraconfig.cache.enable', false)) {
                throw new RuntimeException('Cannot flush cache. Laraconfig cache is not enabled.');
            }

            $store = $this->config->get('laraconfig.cache.store');

            // We will prompt the user if needed, and wait for its confirmation.
            if ($this->shouldPrompt() && !$this->confirms($store)) {
                throw new RuntimeException("Flush of the $store cache has been cancelled.");
            }

            $this->factory->store($store)->flush();
        }

        return $next($data);
    }

    /**
     * Check if the user should be prompted to confirm.
     *
     * @return bool
     */
    protected function shouldPrompt(): bool
    {
        return $this->app->environment('production')
            && ! $this->input->getOption('force');
    }

    /**
     * Confirms if the user
     *
     * @param  string|null  $store
     *
     * @return bool
     */
    protected function confirms(string $store = null): bool
    {
        $store ??= $this->config->get('cache.default');

        return $this->output->confirm("The cache store $store will be flushed completely. Proceed?");
    }
}