<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Subscription;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     * If subscription is inactive, redirect admin to bKash settings page to pay.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Allow disabling subscription enforcement via .env
        if (! (bool) env('SUBSCRIPTION_ENFORCE', true)) {
            return $next($request);
        }

        $sub = Subscription::getCurrent();

        if (! $sub || ! $sub->isActive()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Subscription required'], 402);
            }

            session()->flash('error', 'Subscription inactive. Please configure bKash and complete monthly payment.');
            return redirect()->route('backend.settings.payment.bkash');
        }

        return $next($request);
    }
}
