<?php

namespace AdAstra\Http\Middleware;

use AdAstra\Models\BbValue;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BotBlockRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();
        $modifying = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($modifying  && !$user) {
            $bb = BbValue::where(['field_value' => $request->post(session('bb_field_name'))])->first();
            if (!$bb instanceof BbValue) {
                abort(403);
            }

            $bb->delete();
        }

        return $next($request);
    }
}
