<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                    'result' => null,
                ], 401);
            }

            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Token has expired.',
                'result' => null,
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Token is invalid.',
                'result' => null,
            ], 401);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Token not provided.',
                'result' => null,
            ], 401);
        }
    }
}
