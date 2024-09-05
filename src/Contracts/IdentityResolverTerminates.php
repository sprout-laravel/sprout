<?php

namespace Sprout\Contracts;

use Illuminate\Http\Response;

interface IdentityResolverTerminates
{
    /**
     * @param \Sprout\Contracts\Tenancy<\Sprout\Contracts\Tenant> $tenancy
     * @param \Illuminate\Http\Response                           $response
     *
     * @return void
     */
    public function terminate(Tenancy $tenancy, Response $response): void;
}
