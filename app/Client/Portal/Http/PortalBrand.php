<?php

declare(strict_types=1);

namespace App\Client\Portal\Http;

use App\Client\Site\Copy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A portal request speaks as the trading name customers know (Javaindo), not the staff
 * system's: what Laravel and Filament print from the app name (the password-reset mail's
 * greeting and signature, its sender) follows for the rest of this request only.
 */
class PortalBrand
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = Copy::shortName();
        config(['app.name' => $name, 'mail.from.name' => $name]);

        return $next($request);
    }
}
