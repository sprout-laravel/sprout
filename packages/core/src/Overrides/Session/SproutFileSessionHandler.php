<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Session\FileSessionHandler;
use Illuminate\Support\Carbon;
use Sprout\Concerns\AwareOfTenant;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantAware;
use Sprout\Contracts\TenantHasResources;
use Symfony\Component\Finder\Finder;

/**
 * @method Tenant|TenantHasResources|null getTenant()
 */
final class SproutFileSessionHandler extends FileSessionHandler implements TenantAware
{
    use AwareOfTenant;

    /**
     * @return string
     */
    public function getPath(): string
    {
        if (! $this->hasTenant()) {
            return $this->path;
        }

        /** @var \Sprout\Contracts\Tenant&\Sprout\Contracts\TenantHasResources $tenant */
        $tenant = $this->getTenant();

        return rtrim($this->path, DIRECTORY_SEPARATOR)
               . DIRECTORY_SEPARATOR
               . $tenant->getTenantResourceKey();
    }

    /**
     * {@inheritdoc}
     *
     * @param $sessionId
     *
     * @return string
     */
    public function read($sessionId): string
    {
        if ($this->files->isFile($path = $this->getPath() . '/' . $sessionId) &&
            $this->files->lastModified($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()) {
            return $this->files->sharedGet($path);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function write($sessionId, $data): bool
    {
        $this->files->put($this->getPath() . '/' . $sessionId, $data, true);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        $this->files->delete($this->getPath() . '/' . $sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function gc($lifetime): int
    {
        // @codeCoverageIgnoreStart
        $files = Finder::create()
                       ->in($this->getPath())
                       ->files()
                       ->ignoreDotFiles(true)
                       ->date('<= now - ' . $lifetime . ' seconds');

        $deletedSessions = 0;

        foreach ($files as $file) {
            $this->files->delete($file->getRealPath());
            $deletedSessions++;
        }

        return $deletedSessions;
        // @codeCoverageIgnoreEnd
    }
}
