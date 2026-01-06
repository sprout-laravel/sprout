<?php
declare(strict_types=1);

namespace Sprout\Console\Commands;

use Illuminate\Console\Command;
use Sprout\Support\TenantCacheInvalidator;

/**
 * Clear Tenant Cache Command
 *
 * This command allows you to clear tenant caches either for a specific
 * provider or across all providers.
 *
 * @package Console
 */
final class ClearTenantCache extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'sprout:cache:clear {provider? : The name of the tenant provider}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Clear cached tenants for a provider or all providers';

    /**
     * Execute the console command
     *
     * @param \Sprout\Support\TenantCacheInvalidator $invalidator
     *
     * @return int
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function handle(TenantCacheInvalidator $invalidator): int
    {
        $provider = $this->argument('provider');

        if ($provider !== null) {
            $invalidator->flushProvider($provider);
            $this->components->info(sprintf('Cleared tenant cache for provider: %s', $provider));
        } else {
            $invalidator->flushAll();
            $this->components->info('Cleared all tenant caches');
        }

        return self::SUCCESS;
    }
}
