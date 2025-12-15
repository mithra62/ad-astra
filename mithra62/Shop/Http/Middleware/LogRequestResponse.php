<?php
namespace mithra62\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use mithra62\Shop\Models\ApiLog;

/**
 * 1. Route
 * 2. Payload
 * 3. Response
 * 4. Headers
 * 5. Dates/Times
 * 6. user_id
 */

class LogRequestResponse
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $data = $request->all();

        // If logging an authentication request, mask the password in the log
        if ($request->isMethod('post') && $request->path() === 'api/auth/login' && isset($data['password'])) {
            $data['password'] = 'REDACTED';  // Mask the password
        }

        $response = $next($request);

        $data = [
            'request_route' => $request->getPathInfo(),
            'method' => $request->method(),
            'user_id' => Auth::id(),
            'request_payload' => json_encode($request->all()),
            'request_headers' => json_encode($request->headers->all()),
            'response_payload' => $response->getContent(),
            'response_headers' => json_encode($response->headers->all()),
            'response_status_code' => $response->status()
        ];

        ApiLog::create($data);
        return $response;
    }
}
