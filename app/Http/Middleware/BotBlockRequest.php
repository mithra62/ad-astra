<?php

namespace App\Http\Middleware;

use App\Models\BbValue;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BotBlockRequest
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();
        if (strtolower($request->method()) === 'post' && !$user) {
            $bb = BbValue::where(['field_value' => $request->post('__bb')])->first();
            if (!$bb instanceof BbValue) {
                abort(403);
            }

            $bb->delete();
        }

        return $next($request);
    }
}
