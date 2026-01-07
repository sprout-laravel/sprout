<img src="sprout.png">

![Packagist Version](https://img.shields.io/packagist/v/sprout/bud)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/sprout/bud)

![GitHub](https://img.shields.io/github/license/sprout-laravel/bud)
![Laravel](https://img.shields.io/badge/laravel-11.x-red.svg)
[![codecov](https://codecov.io/gh/sprout-laravel/bud/branch/main/graph/badge.svg?token=FHJ41NQMTA)](https://codecov.io/gh/sprout-laravel/bud)

![Unit Tests](https://github.com/sprout-laravel/bud/actions/workflows/tests.yml/badge.svg)
![Static Analysis](https://github.com/sprout-laravel/bud/actions/workflows/static-analysis.yml/badge.svg)

# Sprout Bud

### Tenant-specific Laravel service config for your Sprout powered Laravel application

Bud is a first-party package for Sprout
that provides functionality allowing tenant-specific configuration for Laravel's core services.

## What does that mean?

Bud provides a [service override](https://sprout.ollieread.com/docs/service-overrides) for several of Laravel's core
services,
which registers a driver called `bud` with that services manager.
When Laravel attempts to resolve something that has the `bud` driver,
Bud will load the corresponding service config for the current tenant, and use that.

Bud supports the following.

- [x] Auth Providers
- [x] Broadcasting Connections
- [x] Cache Stores
- [x] Database Connections
- [x] Filesystem Disks
- [x] Mailers

> [!NOTE]
> If you require configurations that does not require additional values,
> and can be worked out based on the tenant (adding `WHERE` clauses, etc.),
> then please consider one of [Sprouts'
> existing service overrides](https://sprout.ollieread.com/docs/1.x/service-overrides),
> or a [custom one](https://sprout.ollieread.com/docs/1.x/custom-service-override).
