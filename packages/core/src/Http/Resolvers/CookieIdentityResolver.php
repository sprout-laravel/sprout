<?php
declare(strict_types=1);

namespace Sprout\Http\Resolvers;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieJar;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Cookie;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\Middleware\SproutTenantContextMiddleware;
use Sprout\Support\BaseIdentityResolver;
use Sprout\Support\PlaceholderHelper;
use Sprout\Support\ResolutionHook;
use Sprout\TenancyOptions;

/**
 * Cookie Identity Resolver
 *
 * This class is responsible for resolving tenant identities from the current
 * request using cookies.
 *
 * @package Http\Resolvers
 */
final class CookieIdentityResolver extends BaseIdentityResolver
{
    /**
     * The cookie name
     *
     * @var string
     */
    private string $cookie;

    /**
     * Additional options for the cookie
     *
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * Create a new instance
     *
     * @param string                                $name
     * @param string|null                           $cookie
     * @param array<string, mixed>                  $options
     * @param array<\Sprout\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, ?string $cookie = null, array $options = [], array $hooks = [])
    {
        parent::__construct($name, $hooks);

        $this->cookie  = $cookie ?? '{Tenancy}-Identifier';
        $this->options = $options;
    }

    /**
     * Get the cookie name
     *
     * @return string
     */
    public function getCookieName(): string
    {
        return $this->cookie;
    }

    /**
     * Get the extra cookie options
     *
     * @return array<string, mixed>
     */
    public function getCookieOptions(): array
    {
        return $this->options;
    }

    /**
     * Get the cookie name with replacements
     *
     * This method returns the name of the cookie returned by
     * {@see self::getCookieName()}, except it replaces <code>{tenancy}</code>
     * and <code>{resolver}</code> with the name of the tenancy, and resolver,
     * respectively.
     *
     * You can use an uppercase character for the first character, <code>{Tenancy}</code>
     * and <code>{Resolver}</code>, and it'll be run through {@see \ucfirst()}.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     *
     * @return string
     */
    public function getRequestCookieName(Tenancy $tenancy): string
    {
        return PlaceholderHelper::replace(
            $this->getCookieName(),
            [
                'tenancy'  => $tenancy->getName(),
                'resolver' => $this->getName(),
            ]
        );
    }

    /**
     * Get an identifier from the request
     *
     * Locates a tenant identifier within the provided request and returns it.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string|null
     *
     * @throws \Sprout\Exceptions\CompatibilityException
     */
    public function resolveFromRequest(Request $request, Tenancy $tenancy): ?string
    {
        if (TenancyOptions::shouldEnableOverride($tenancy, 'cookie')) {
            throw CompatibilityException::make('resolver', $this->getName(), 'service override', 'cookie');
        }

        /**
         * This is unfortunately here because of the ludicrous return type
         *
         * @var string|null $cookie
         */
        $cookie = $request->cookie($this->getRequestCookieName($tenancy));

        if ($cookie !== null && $this->getSprout()->isCurrentHook(ResolutionHook::Routing)) {
            // If we're processing during the routing hook, the cookies aren't
            // decrypted yet, so we have to manually decrypt them
            $cookie = $this->decryptCookie($this->getRequestCookieName($tenancy), $cookie);
        }

        return $cookie;
    }

    /**
     * Perform setup actions for the tenant
     *
     * When a tenant is marked as the current tenant within a tenancy, this
     * method will be called to perform any necessary setup actions.
     * This method is also called if there is no current tenant, as there may
     * be actions needed.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant|null          $tenant
     *
     * @phpstan-param Tenant|null                    $tenant
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        if ($tenant !== null && $tenancy->check()) {
            /**
             * @var array{name:string, value:string} $details
             */
            $details = $this->getCookieDetails(
                [
                    'name'  => $this->getRequestCookieName($tenancy),
                    'value' => $tenancy->identifier(),
                ]
            );

            $this->getApp()
                 ->make(CookieJar::class)
                 ->queue(Cookie::make(...$details));
        } else if ($tenant === null) {
            $this->getApp()
                 ->make(CookieJar::class)
                 ->expire($this->getRequestCookieName($tenancy));
        }
    }

    /**
     * Get the details for cookie creation, with defaults
     *
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore
     */
    private function getCookieDetails(array $details): array
    {
        if (isset($this->options['minutes'])) {
            $details['minutes'] = $this->options['minutes'];
        }

        if (isset($this->options['path'])) {
            $details['path'] = $this->options['path'];
        }

        if (isset($this->options['domain'])) {
            $details['domain'] = $this->options['domain'];
        }

        if (isset($this->options['secure'])) {
            $details['secure'] = $this->options['secure'];
        }

        if (isset($this->options['http_only'])) {
            $details['http_only'] = $this->options['http_only'];
        }

        if (isset($this->options['same_site'])) {
            $details['same_site'] = $this->options['same_site'];
        }

        return $details;
    }

    /**
     * Decrypt a cookie value
     *
     * @param string $key
     * @param string $cookie
     *
     * @return string|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private function decryptCookie(string $key, string $cookie): ?string
    {
        // The cookie is passed as a base64 encoded JSON string
        $cookieJson = base64_decode($cookie);

        if (json_validate($cookieJson) === false) {
            // This isn't JSON, so we can assume the original value is correct
            return $cookie;
        }

        // Get the encrypter
        $encrypter = $this->getApp()->make(Encrypter::class);

        // Decrypt the actual cookie value
        $value = $encrypter->decrypt($cookie, false);

        // And then "validate it"? This actually strips the value, so I'm not
        // sure validate is the correct name, but I didn't name it..
        return CookieValuePrefix::validate($key, $value, $encrypter->getAllKeys());
    }
}
