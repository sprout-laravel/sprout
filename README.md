<img src="sprout.png">

![Packagist Version](https://img.shields.io/packagist/v/sprout/sprout)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/sprout/sprout)

![GitHub](https://img.shields.io/github/license/sprout-laravel/sprout)
![Laravel](https://img.shields.io/badge/laravel-12.x-red.svg)
[![codecov](https://codecov.io/gh/sprout-laravel/sprout/branch/main/graph/badge.svg?token=FHJ41NQMTA)](https://codecov.io/gh/sprout-laravel/sprout)

[![Unit Tests](https://github.com/sprout-laravel/sprout/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/sprout-laravel/sprout/actions/workflows/tests.yml?query=branch%3Amain)
[![Static Analysis](https://github.com/sprout-laravel/sprout/actions/workflows/static-analysis.yml/badge.svg?branch=main)](https://github.com/sprout-laravel/sprout/actions/workflows/static-analysis.yml?query=branch%3Amain)

> [!WARNING]
> You are viewing the upcoming `2.0` release, which includes a whole host of new features, and a significant
> change in the repository structure.

# Sprout for Laravel

A flexible, seamless, and easy to use multitenancy solution for Laravel

Sprout is a multitenancy package for Laravel that fits seamlessly into your application.
It provides a whole host of features, with the flexibility to allow you to add your own.
You can read all about Sprouts features and how to get started in
the [documentation](https://sprout.ollieread.com/docs/1.x).

## Features

Sprout comes out of the box with the following features:

- **Tenant Identification**:
  Sprout can identify tenants by _subdomain_, _path_, _header_, _cookie_ or session.
  It can do this immediately once a route is matched, or during the middleware stack.
- **Multiple Tenancies**:
  Sprout can handle multiple tenancies, as in multiple different models that are tenants.
- **Tenant Storage Disks**:
  Sprout comes with a service override that allows you to create a storage disk that
  always points to the current tenant's storage directory.
- **Tenant Sessions & Cookies**:
  If you're identifying tenants via a method that uses the URL (subdomain or path),
  cookies, and therefore the session cookie, will be automatically scoped to the current tenant.
- **Tenant Cache Stores**:
  Just like with storage disks, Sprout allows you to create a cache store that always
  returns the current tenants cache.
- **Tenant Aware Jobs**:
  When a job is dispatched, Sprout will make sure that any tenancies that are active are
  recreated when the job is processing, along with their current tenants.
- **Tenant Password Resets**:
  If you're following a model where users belong to a single tenant, you'll also want to
  make sure that password resets are scoped to the tenant.
  Sprout can do this for you.
- **Tenant-Specific Configuration**:
  Tenants can carry their own configuration for Laravel's core driver-based services —
  database connections, cache stores, filesystem disks, mailers, broadcast channels and
  auth providers — applied automatically when the tenant is active.
- **Automatic Scoping**:
  As well as all the automated scoping of storage disks, cache stores, jobs, password resets
  and so on, Sprout also comes with a set of functionality for automatically scoping models, during creation and
  querying.

There are also two upcoming first-party addons for Sprout:

- [**Sprout Seedling**](https://github.com/sprout-laravel/seedling):
  Seedling brings multi-tenant database support to your Laravel application,
  building on Sprout's tenant-specific configuration to provide per-tenant connections
  along with a batch of supporting functionality for managing tenant databases.
- [**Sprout Terra**](https://github.com/sprout-laravel/terra):
  Terra brings _domain_-based identification to
  Sprout, allowing you to identify tenants based on the domain they are accessing your application from.
  Just like with Seedling, it also comes with a bunch of supporting functionality for dealing with tenant domains.

## FAQ

### Does Sprout support tenant-specific databases?

It will do through [Sprout Seedling](https://github.com/sprout-laravel/seedling), which is currently in development.

### Why are tenant-specific databases handled by an addon?

I didn't want to just provide a barebones implementation of tenant-specific database handling, as there's a lot more
to think about than just changing the connection on a model.
So, I wanted to provide the feature with a bunch of opt-in supporting functionality, which is why it's an addon.

### Does sprout support domain-based identification?

It will do through [Sprout Terra](https://github.com/sprout-laravel/terra), which is currently in development.

### Why are domain-based tenants handled by an addon?

The same as with Seedling, I wanted to provide a bunch of supporting functionality to make managing domain-based tenants
easier, which is why it's an addon.

### Why should I use Sprout over other multitenancy packages?

It will mostly come down to preference, but I've tried to make Sprout as flexible as possible, without compromising on
any features and functionality.
It is built to be as seamless as possible, avoiding hacky workarounds and providing a clean API.
No magic to dynamically modify things, or artificial limitations, whether side effects or not.

### Why did you build Sprout?

I found that the existing multitenancy packages for Laravel were either too opinionated, not flexible enough, or
lacking in features.
I wanted to build something that was flexible, feature-rich, and easy to use.
