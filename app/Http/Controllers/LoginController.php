<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            $user->load('userRole');
            $roleId = optional($user->userRole)->role_id;

            if ($roleId == 1 || $roleId == 2) {
                return response()->json([
                    'message' => 'Login successful',
                    'user' => $user,
                    'status' => 200
                ], 200);
            }
            else {
                return response()->json(['error' => 'Unauthorized', 'staus' => 401]);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }
}