<?php
declare(strict_types=1);

namespace Sprout\Support;

enum ResolutionHook
{
    case Bootstrapping;

    case Booting;

    case Routing;

    case Middleware;
}
