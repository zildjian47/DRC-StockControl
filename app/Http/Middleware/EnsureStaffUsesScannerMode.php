<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUsesScannerMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isPpic() && $request->query('mode') !== 'scanner') {
            $routeName = $request->route()?->getName();
            if ($routeName) {
                return redirect()->route($routeName, array_merge($request->query(), ['mode' => 'scanner']));
            }

            return redirect()->route('scanner.portal');
        }

        return $next($request);
    }
}