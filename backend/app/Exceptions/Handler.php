<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json(['message' => 'API endpoint not found.'], 404);
            }
            
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json(['message' => 'HTTP method not allowed.'], 405);
            }

            if ($exception instanceof ThrottleRequestsException) {
            return response()->json([
                'message' => 'Too many requests. Please slow down and try again later.',
                'retry_after_seconds' => $exception->getHeaders()['Retry-After'] ?? null,
            ], 429);
        }
        }
        
        return parent::render($request, $exception);
    }
}
