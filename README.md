<img src="sprout.png">

![Packagist Version](https://img.shields.io/packagist/v/sprout/sprout)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/sprout/sprout)
![GitHub](https://img.shields.io/github/license/sprout-laravel/sprout)
![Laravel](https://img.shields.io/badge/laravel-10.x-red.svg)

Main:

[![codecov](https://codecov.io/gh/sprout-laravel/sprout/branch/main/graph/badge.svg?token=FHJ41NQMTA)](https://codecov.io/gh/sprout-laravel/sprout)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fsmplphp%2Fcore%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/sprout-laravel/sprout/main)

# Sprout for Laravel
### A flexible, seamless and easy to use multitenancy solution for Laravel

This package is currently under development. Check back in the future, or check out my [twitter](https://ollieread.com), [mastodon](https://phpc.social/@ollieread) or [discord](https://discord.gg/wPHGrUh) for updates.

## A quick FAQ
Here's a little FAQ that hopefully answers any questions you have for now.

### Why are you building yet another multitenancy package for Laravel?
I feel like the currently available solutions leave something to be desired, as they're
either very opinionated or unnecessarily inflexible.

### What sets this package apart?
It provides improvements on what is currently available, such as:

* It is more flexible.
* It is far more seamless.
* It provides a greater degree of separation between your application logic and business logic.
* It provides supporting functionality, either within this package or as an optional addon.
* It doesn't tie you into one particular way of doing things.
* It doesn't limit what you can do with Laravel.
* It doesn't use magic to dynamically alter things, like the current default connection, because that really obfuscates what's happening.

### Does it provide single and multi-database support?
Yes, either one on their own or as a combination.

### Can I use dependency injection with my controller constructors?
Of course. Laravels container can successfully resolve controller dependencies that require a tenant, and inject them into the constructor, straight out of the box.

### Wasn't this package originally premium?
It was, but I realised that I care far more about people having options than I do about
getting paid.

### Does this supersede the course?
No. This package is the code that I used to write the course, except it has been 
slightly modified so that it can function as a package.

While this package provides a solid base for your application, there are going to be
times when it's better to write a custom solution.
