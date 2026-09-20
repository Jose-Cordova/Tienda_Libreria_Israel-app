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
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.exists' => 'No encontramos ningún usuario registrado con este correo.'
        ]);

        //Obtenemos el usuario por email
        $user = User::where('email', $request->email)->first();

        // Validamos que el usuario esté ACTIVO
        if ($user->estado === 'INACTIVO') {
            return response()->json([
                'message' => 'Tu cuenta se encuentra inactiva. No puedes restablecer tu contraseña. Comunícate con el administrador.'
            ], 403);
        }

        if ($user->estado === 'PENDIENTE') {
            return response()->json([
                'message' => 'Tu cuenta aún está pendiente de activación. Revisa tu correo de invitación inicial para establecer tu contraseña.'
            ], 403);
        }

        if ($user->estado !== 'ACTIVO') {
            return response()->json([
                'message' => 'Tu cuenta no está activa para restablecer contraseña.'
            ], 403);
        }

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
                // Regla de validación para cualquier carácter especial
                'regex:/[^a-zA-Z0-9]/'
            ]
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex' => 'La contraseña no cumple con los requisitos de seguridad.'
        ]);

        // Validamos que el usuario exista y esté ACTIVO
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'No se encontró un usuario con ese correo electrónico.'
            ], 404);
        }

        if ($user->estado !== 'ACTIVO') {
            return response()->json([
                'message' => 'Tu cuenta no se encuentra activa. No es posible restablecer la contraseña.'
            ], 403);
        }

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
