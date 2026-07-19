<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPassword;

class ForgotPasswordController extends Controller
{
    //Funcion para enviar enlace de restablecimiento al correo del ususario
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        //Obtenemos el usuario por email
        $user = User::where('email', $request->email)->first();
        //Generamos token de restablecimiento
        $token = Password::broker('users')->createToken($user);
        //Enviamos al correo con el token
        Mail::to($user->email)->send(new ResetPassword($user, $token));

        return response()->json([
            'message' => 'Recibirás un enlace para restablecer tu contraseña.'
        ], 200);
    }

    //Funcion para restablecer la contraseña usando el token y email
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/'
            ]
        ]);

        //Intentamos restablecer la contraseña
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password){
                $user->password = bcrypt($password);
                $user->save();
            }
        );

        //Verificamos el resultado del restablecimiento
        if($status === Password::PASSWORD_RESET){
            return response()->json([
                'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.'
            ], 200);
        }
        //Si el token es invalido o expiro
        return response()->json([
            'message' => 'El enlace de restablecimiento es inválido o ha expirado.'
        ], 422);
    }
}
