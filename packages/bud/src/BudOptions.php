<?php
declare(strict_types=1);

namespace Sprout\Bud;

use Sprout\Core\Contracts\Tenancy;

/**
 *
 */
final class BudOptions
{
    /**
     * @param string $store
     *
     * @return string[]
     */
    public static function useDefaultStore(string $store): array
    {
        return [
            'bud:store.default' => $store,
        ];
    }

    /**
     * @param string $store
     *
     * @return string[]
     */
    public static function alwaysUseStore(string $store): array
    {
        return [
            'bud:store.fixed' => $store,
        ];
    }

    /**
     * @param \Sprout\Core\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function hasDefaultStore(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption('bud:store.default');
    }

    /**
     * @param \Sprout\Core\Contracts\Tenancy<*> $tenancy
     *
     * @return string|null
     */
    public static function getDefaultStore(Tenancy $tenancy): ?string
    {
        $store = $tenancy->optionConfig('bud:store.default');

        if (is_string($store)) {
            return $store;
        }

        return null;
    }

    /**
     * @param \Sprout\Core\Contracts\Tenancy<*> $tenancy
     *
     * @return bool
     */
    public static function isLockedToStore(Tenancy $tenancy): bool
    {
        return $tenancy->hasOption('bud:store.locked');
    }

    /**
     * @param \Sprout\Core\Contracts\Tenancy<*> $tenancy
     *
     * @return string|null
     */
    public static function getLockedStore(Tenancy $tenancy): ?string
    {
        $store = $tenancy->optionConfig('bud:store.locked');

        if (is_string($store)) {
            return $store;
        }

        return null;
    }
}
