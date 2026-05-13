<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $credenciales = $request->only('email', 'password');

            if (!$token = Auth::guard('api')->attempt($credenciales)) {
                return response()->json([
                    'mensaje' => 'Credenciales incorrectas.'
                ], 401);
            }

            return $this->responderConToken($token);

        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    public function me()
    {
        try {
            $usuario = Auth::guard('api')->user();

            return response()->json($usuario, 200);

        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    public function logout()
    {
        try {
            Auth::guard('api')->logout();

            return response()->json([
                'mensaje' => 'Sesión cerrada correctamente.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    public function refresh()
    {
        try {
            $token = Auth::guard('api')->refresh();

            return $this->responderConToken($token);

        } catch (\Exception $e) {
            return response()->json([
                'mensaje' => 'Error interno en el servidor.'
            ], 500);
        }
    }

    protected function responderConToken($token)
    {
        return response()->json([
            'token'     => $token,
            'tipo'      => 'bearer',
            'expira_en' => Auth::guard('api')->factory()->getTTL() * 60 . ' segundos'
        ], 200);
    }
}
