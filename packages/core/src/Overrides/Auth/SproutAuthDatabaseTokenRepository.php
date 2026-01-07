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
 * {@see \Illuminate\Auth\Passwords\DatabaseTokenRepository} to query based on
 * the current tenant.
 *
 * @package Overrides
 */
class SproutAuthDatabaseTokenRepository extends DatabaseTokenRepository
{
    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /** @infection-ignore-all */
    public function __construct(
        Sprout              $sprout,
        ConnectionInterface $connection,
        HasherContract      $hasher,
                            $table,
                            $hashKey,
                            $expires = 60,
                            $throttle = 60
    )
    {
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
     * @return \Sprout\Contracts\Tenancy<*>
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
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
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    protected function getPayload($email, #[SensitiveParameter] $token): array
    {
        if (! $this->sprout->withinContext()) {
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
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
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
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return object|null
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    protected function getExistingTenantedRecord(CanResetPasswordContract $user): ?object
    {
        return $this->getTenantedQuery($user->getEmailForPasswordReset())->first();
    }

    /**
     * Delete all existing reset tokens from the database.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return int
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    protected function deleteExisting(CanResetPasswordContract $user): int
    {
        return $this->getTenantedQuery($user->getEmailForPasswordReset())->delete();
    }

    /**
     * Determine if a token record exists and is valid.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     * @param string                                      $token
     *
     * @return bool
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function exists(CanResetPasswordContract $user, #[SensitiveParameter] $token): bool
    {
        $record = (array)$this->getExistingTenantedRecord($user);

        return $record &&
               ! $this->tokenExpired($record['created_at']) &&
               $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the given user recently created a password reset token.
     *
     * @param \Illuminate\Contracts\Auth\CanResetPassword $user
     *
     * @return bool
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function recentlyCreatedToken(CanResetPasswordContract $user): bool
    {
        $record = (array)$this->getExistingTenantedRecord($user);

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }
}
