<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    
public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'user_id' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Please enter your user ID and password.',
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $credentials = $validator->validated();

        // Check that the user is active
        $user = User::where('user_id', $credentials['user_id'])
            ->where('bit_delete', 0)
            ->first();

        if (!$user || !Hash::check(
            $credentials['password'],
            $user->password
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user ID or password.',
                'result' => null,
            ], 401);
        }

        // Generate JWT for the Eloquent User model
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'result' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ],
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Login failed', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong. Please try again later.',
            'result' => null,
        ], 500);
    }
}
public function me()
{
    $user = auth('api')->user();


    $result = DB::table('users as u')
    ->join('employees as e', 'u.employee_id', '=', 'e.id')
    ->join('roles as r', 'u.role_id', '=', 'r.id')
    ->where('u.id', $user->id)
    ->select(
        'e.emp_name',
        'e.emp_email',
        'r.name as role_name',
        'u.role_id as role_id'
    )
    ->first();


    return response()->json([
        'success' => true,
        'message' => 'User details fetched successfully.',
        'user' => $result
    ], 200);
}

    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
