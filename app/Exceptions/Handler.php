<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Log;
use Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
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

        });

        $this->renderable(function(TokenInvalidException $e, $request){
            return Response::json([
                'message' => 'Invalid Token',
                'code' => 401
            ],401);
        });
        $this->renderable(function (TokenExpiredException $e, $request) {
            return Response::json([
                'message' => 'Token Expired',
                'code' => 201
            ],201);
        });

        /* $this->renderable(function (JWTException $e, $request) { */
        /*     return Response::json([ */
        /*         'message' => 'Token Not Parsed', */
        /*         'code' => 401 */
        /*     ],401); */
        /* }); */
    }
}
