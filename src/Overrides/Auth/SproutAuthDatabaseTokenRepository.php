<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use SensitiveParameter;
use Sprout\Contracts\Tenancy;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

/**
 * Sprout Auth Database Token Repository
 *
 * This is a database token repository that wraps the default
 * {@see DatabaseTokenRepository} to query based on
 * the current tenant.
 */
class SproutAuthDatabaseTokenRepository extends DatabaseTokenRepository
{
    /**
     * @var Sprout
     */
    private Sprout $sprout;

    /** @infection-ignore-all */
    public function __construct(
        Sprout              $sprout,
        ConnectionInterface $connection,
        HasherContract      $hasher,
        string              $table,
        string              $hashKey,
        int                 $expires = 60,
        int                 $throttle = 60,
    ) {
        parent::__construct($connection, $hasher, $table, $hashKey, $expires, $throttle);
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
     * Determine if a token record exists and is valid.
     *
     * @param CanResetPasswordContract $user
     * @param string                   $token
     *
     * @return bool
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function exists(CanResetPasswordContract $user, #[SensitiveParameter] $token): bool
    {
        /** @var array{token?: string, created_at?: string} $record */
        $record = (array) $this->getExistingTenantedRecord($user);

        return ! empty($record)
               && isset($record['token'], $record['created_at'])
               && ! $this->tokenExpired($record['created_at'])
               && $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the given user recently created a password reset token.
     *
     * @param CanResetPasswordContract $user
     *
     * @return bool
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function recentlyCreatedToken(CanResetPasswordContract $user): bool
    {
        /** @var array{token?: string, created_at?: string} $record */
        $record = (array) $this->getExistingTenantedRecord($user);

        return ! empty($record)
               && isset($record['created_at'])
               && $this->tokenRecentlyCreated($record['created_at']);
    }

    /**
     * @return \Sprout\Contracts\Tenancy<*>
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    protected function getTenancy(): Tenancy
    {
        $tenancy = $this->sprout->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        if (! $tenancy->check()) {
            throw TenantMissingException::make($tenancy->getName());
        }

        return $tenancy;
    }

    /**
     * Build the record payload for the table.
     *
     * @param string $email
     * @param string $token
     *
     * @return array<string, mixed>
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    protected function getPayload($email, #[SensitiveParameter] $token): array
    {
        if (! $this->sprout->withinContext()) {
            /** @var array<string, mixed> */
            return parent::getPayload($email, $token);
        }

        $tenancy = $this->getTenancy();

        return [
            'tenancy'    => $tenancy->getName(),
            'tenant_id'  => $tenancy->key(),
            'email'      => $email,
            'token'      => $this->hasher->make($token),
            'created_at' => new Carbon(),
        ];
    }

    /**
     * Get the tenanted query
     *
     * @param string $email
     *
     * @return Builder
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    protected function getTenantedQuery(string $email): Builder
    {
        if (! $this->sprout->withinContext()) {
            return $this->getTable()->where('email', $email);
        }

        $tenancy = $this->getTenancy();

        return $this->getTable()
                    ->where('tenancy', $tenancy->getName())
                    ->where('tenant_id', $tenancy->key())
                    ->where('email', $email);
    }

    /**
     * Get the record for a user
     *
     * @param CanResetPasswordContract $user
     *
     * @return object|null
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    protected function getExistingTenantedRecord(CanResetPasswordContract $user): ?object
    {
        return $this->getTenantedQuery($user->getEmailForPasswordReset())->first();
    }

    /**
     * Delete all existing reset tokens from the database.
     *
     * @param CanResetPasswordContract $user
     *
     * @return int
     *
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    protected function deleteExisting(CanResetPasswordContract $user): int
    {
        return $this->getTenantedQuery($user->getEmailForPasswordReset())->delete();
    }
}
