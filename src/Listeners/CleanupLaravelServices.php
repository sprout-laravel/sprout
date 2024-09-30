<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Filesystem\FilesystemManager;
use Sprout\Events\CurrentTenantChanged;

final class CleanupLaravelServices
{
    /**
     * @var \Illuminate\Filesystem\FilesystemManager
     */
    private FilesystemManager $filesystemManager;

    public function __construct(FilesystemManager $filesystemManager)
    {
        $this->filesystemManager = $filesystemManager;
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        $this->purgeFilesystemDisks();
    }

    private function purgeFilesystemDisks(): void
    {
        // If we're not overriding the storage service we can exit early
        if (config('sprout.services.storage', false) === false) {
            return;
        }

        /** @var array<string, array<string, mixed>> $diskConfig */
        $diskConfig = config('filesystems.disks', []);

        // If any of the disks have the 'sprout' driver we need to purge them,
        // if they exist, so we don't end up leaking tenant information
        foreach ($diskConfig as $disk => $config) {
            if (($config['driver'] ?? null) === 'sprout') {
                $this->filesystemManager->forgetDisk($disk);
            }
        }
    }
}
