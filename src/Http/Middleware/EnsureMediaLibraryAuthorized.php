<?php

namespace Ayvazyan10\Imagic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureMediaLibraryAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('imagic.media_library.authorization_gate');

        abort_unless(
            $request->user() !== null && (! $gate || Gate::forUser($request->user())->allows($gate)),
            403,
        );

        return $next($request);
    }
}
