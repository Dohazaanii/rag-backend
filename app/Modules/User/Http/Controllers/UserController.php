<?php
namespace App\Modules\User\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;

class UserController extends Controller
{

public function register(Request $request)
{
$request->validate([
    'first_name' => 'required|string|max:50',
    'last_name'  => 'required|string|max:50',
    'email'      => 'required|email|unique:users,email',
    'department' => 'required|string|max:100',   
    'password'   => 'required|min:8|confirmed',
]);

$user = \App\Models\User::create([
    'first_name' => $request->first_name,
    'last_name'  => $request->last_name,
    'name'       => $request->first_name . ' ' . $request->last_name,
    'email'      => $request->email,
    'department' => $request->department,        
    'password'   => bcrypt($request->password),
]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message'      => 'Compte créé avec succès',
        'user'         => $user,
        'access_token' => $token,
        'token_type'   => 'Bearer',
    ], 201);
}

public function login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json([
            'message' => 'Email or password is incorrect'
        ], 401);
    }

    $user = $request->user();

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message'      => 'Connecté avec succès',
        'user'         => $user,   
        'access_token' => $token,
        'token_type'   => 'Bearer',
    ]);
}

public function logout(Request $request)
{
    $request->user()->tokens()->delete();

    return response()->json([
        'message' => 'Déconnecté avec succès'
    ]);
}
}