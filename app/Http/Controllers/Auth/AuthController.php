<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request){
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        $credenciales = $request->only('email','password');

        // Evaluamos si las credenciales son incorrectas
        if(!$token = Auth::attempt($credenciales)){
           return response()->json([
            'message'=> 'Credenciales inválidas'
           ], 401);
        }

        // Obtener el usuario autenticado
        $user = auth()->user();

        // Validar el estado del usuario
        if ($user->estado === 'INACTIVO') {
            auth()->logout();
            return response()->json([
                'message' => 'Tu cuenta se encuentra inactiva. Comunícate con el administrador.'
            ], 403);
        }

        if ($user->estado === 'PENDIENTE') {
            auth()->logout();
            return response()->json([
                'message' => 'Tu cuenta está pendiente de activación. Revisa tu correo electrónico para establecer tu contraseña.'
            ], 403);
        }

        if ($user->estado !== 'ACTIVO') {
            auth()->logout();
            return response()->json([
                'message' => 'Acceso denegado. No tienes una cuenta activa en el sistema.'
            ], 403);
        }

        // En caso de exitoso retornamos el token
        return $this->responseWithToken($token);
    }

    public function register(Request $request){
      //Validamos datos a través de Request
      $validator = Validator::make($request->all(),[
          'name' => 'required|string|max:191',
          'email' => 'required|string|email|max:191|unique:users',
          'password' => 'required|string|min:8'
      ]);
      if($validator->fails()){
            return response()->json($validator->errors(),422);
      }
      //Creamos el usuario
      $user = User::create([
          'name' => $request->name,
          'email' => $request->email,
          'password' => Hash::make($request->password),
          'estado' => 'ACTIVO'
      ]);

      //Asignar rol por defecto
        $user->assignRole('VENDEDOR');
      //Generamos el token
      $token = JWTAuth::fromUser($user);
      //Retornamos la respuesta
      return response()->json([
          'message' => 'Usuario registrado correctamente',
          'user' => $user,
          'access_token' => $token,
          'token_type' => 'bearer',
           'expires_in' => auth()->factory()->getTTL() * 60
      ],201);
    }

    protected function responseWithToken($token){
        // Obtener el usuario autenticado y cargar sus roles asignados
        $user = auth()->user();
        if ($user) {
            $user->load('roles');
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $user,
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function me(){
        // Cargar los roles del usuario autenticado al consultar /auth/me
        $user = auth()->user();
        if (!$user || $user->estado !== 'ACTIVO') {
            auth()->logout();
            return response()->json([
                'message' => 'Tu cuenta no está activa.'
            ], 403);
        }

        $user->load('roles');
        return response()->json($user);
    }

    //Método para invalidar un token (logout)
    public function logout(){
        auth()->logout();
        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    //Método para refrescar el token
    public function refresh(){
        $user = auth()->user();
        if (!$user || $user->estado !== 'ACTIVO') {
            auth()->logout();
            return response()->json([
                'message' => 'Tu cuenta no está activa.'
            ], 403);
        }

        return $this->responseWithToken(auth()->refresh());
    }
}
