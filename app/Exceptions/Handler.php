<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }


    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->ajax()) {
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Session expired or unauthorized. Please login again.',
                ], 401);
            }

            if ($exception instanceof TokenMismatchException) {
                return response()->json([
                    'message' => 'Session expired. Please refresh the page and try again.',
                ], 419);
            }

            if ($exception instanceof SpatieUnauthorizedException) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            $message = $request->method() . ' Method not allowed for this action.';

            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => $message], 405);
            }

            return redirect()->back()->with('errorMessage', $message);
        }

        return parent::render($request, $exception);
    }

}
