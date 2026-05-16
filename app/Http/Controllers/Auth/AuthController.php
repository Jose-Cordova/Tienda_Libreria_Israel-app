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
        $credenciales = $request->only('email','password');
        //Evaluamos si no se obtiene un token válido
        if(!$token = Auth::attempt($credenciales)){
           return response()->json([
            'message'=> 'Credenciales inválidas'
           ], 401);
        }
        //En caso de exitoso retornamos el token
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
          'password' => Hash::make($request->password)
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
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => auth()->user(),
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    public function me(){
        return response()->json(auth()->user());
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
    return $this->responseWithToken(auth()->refresh());
    }
}
