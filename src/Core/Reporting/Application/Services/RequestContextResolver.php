<?php

namespace TLabsCo\CrashGuard\Core\Reporting\Application\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class RequestContextResolver
{
    /**
     * Resolve request context fields from the current HTTP request and auth state.
     * Returns an empty array when running in console context.
     *
     * @return array{user_id?: string, user_email?: string, ip?: string, user_agent?: string, route?: string}
     */
    public function resolve(): array
    {
        if (App::runningInConsole()) {
            return [];
        }

        $request = Request::instance();
        $user = Auth::user();

        return array_filter([
            'user_id' => $user?->getKey() !== null ? (string) $user->getKey() : null,
            'user_email' => $user?->getAttribute('email'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName() ?? $request->path(),
        ]);
    }
}
