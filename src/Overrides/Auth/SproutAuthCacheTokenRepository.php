<?php
declare(strict_types=1);

namespace Sprout\Core\Overrides\Auth;

use Illuminate\Auth\Passwords\CacheTokenRepository;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Sprout\Core\Contracts\TenantHasResources;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\Sprout;

class SproutAuthCacheTokenRepository extends CacheTokenRepository
{
    /**
     * @var \Sprout\Core\Sprout
     */
    private Sprout $sprout;

    private string $prefix;

    /** @infection-ignore-all */
    public function __construct(
        Sprout         $sprout,
        Repository     $cache,
        HasherContract $hasher,
        string         $hashKey,
        int            $expires = 3600,
        int            $throttle = 60,
        string         $prefix = ''
    )
    {
        parent::__construct($cache, $hasher, $hashKey, $expires, $throttle);

        $this->prefix = $prefix;
        $this->sprout = $sprout;
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function getThrottle(): int
    {
        return $this->throttle;
    }

    /**
     * @return string
     *
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    public function getPrefix(): string
    {
        $prefix = $this->getTenantedPrefix();

        if (empty($prefix)) {
            return $this->prefix;
        }

        if (! empty($this->prefix)) {
            $prefix = $this->prefix . '.' . $prefix;
        }

        return $prefix;
    }

    /**
     * @return string
     *
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    protected function getTenantedPrefix(): string
    {
        if (! $this->sprout->withinContext()) {
            return '';
        }

        $tenancy = $this->sprout->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        if (! $tenancy->check()) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var \Sprout\Core\Contracts\Tenant $tenant */
        $tenant = $tenancy->tenant();

        return $tenancy->getName() . '.' . ($tenant instanceof TenantHasResources ? $tenant->getTenantResourceKey() : $tenant->getTenantKey());
    }

    /**
     * Determine the cache key for the given user.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return string
     *
     * @throws \Sprout\Core\Exceptions\TenancyMissingException
     * @throws \Sprout\Core\Exceptions\TenantMissingException
     */
    public function cacheKey(CanResetPasswordContract $user): string
    {
        return hash('sha256', $this->getPrefix() . $user->getEmailForPasswordReset());
    }
}
