<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try{
            $query = User::with('roles');

            //Filtro por busqueda (nombre o email)
            if($request->filled('search')){
                $search = $request->search;
                $query->where(function ($q) use ($search){
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                });
            }
            //Filtro por estado
            if($request->filled('estado')){
                $query->where('estado', $request->estado);
            }
            //Filtro por rol
            if($request->filled('role')){
                $query->whereHas('roles', function($q) use ($request){
                    $q->where('name', $request->role);
                });
            }

            $perPage = $request->get('per_page', 10);
            $users = $query->orderBy('id', 'desc')->paginate($perPage);
            //Trasformar para incluir el nombre del rol
            $users->getCollection()->transform(function($user){
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'estado' => $user->estado,
                    'rol' => $user->roles->first()->name ?? 'SIN ROL',
                    'created_at' => $user->created_at
                ];
            });

            return response()->json($users, 200);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error al obtener los usuarios.',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRequest $request)
    {
        try{
            //Generamos contraseña temporal y token
            $tempPassword = Str::random(12);
            $token = Str::uuid()->toString() . '-' . now()->timestamp;

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($tempPassword),
                'estado' => 'PENDIENTE',
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addHours(24)
            ]);
            //Asignamos rol
            $user->assignRole($request->role);
            //Enviar correo de invitacion
            Mail::to($user->email)->send(new UserInvitation($user, $token));

            return response()->json([
                'message' => 'Usuario creado. Se ha enviado una invitación al correo.',
                'user' => $user->load('roles')
            ], 201);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, string $id)
    {
        try{
            $user = User::findOrFail($id);

            //Protegemos el usuario master
            if($user->id === 1){
                return response()->json([
                    'message' => 'No se puede modificar al usuario master.'
                ], 403);
            }

            $user->update([
                'name' => $request->name,
                'email' => $request->email
            ]);
            //Actualizar rol sincronizar
            $user->syncRoles([$request->role]);

            return response()->json([
                'message' => 'Usuario actualizado correctamente.',
                'user' => $user->load('roles')
            ], 200);


        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'error' => $e->getMessage()
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $user = User::findOrFail($id);

            if($user->id === 1){
                return response()->json([
                    'message' => 'No se puede eliminar al usuario master'
                ], 403);
            }
            if($user->estado === 'ACTIVO'){
                return response()->json([
                    'message' => 'No se puede eliminar un usuario activo.'
                ], 422);
            }

            //Verificamos si el usuario tiene registros asociados
            $hasRecords = User::where('id', $user->id)
                ->where(function ($query){
                    $query->whereHas('ventas')
                        ->orWhereHas('compras')
                        ->orWhereHas('notas');
                })->exists();
            if($hasRecords){
                return response()->json([
                    'message' => 'No se puede eliminar el usuario porque tiene registros asociados'
                ], 422);
            }

            $user->delete();
            return response()->json([
                'message' => 'Usuario eliminado correctamente.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'error' => $e->getMessage()
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Funcion para cambiar estados
    public function changeStatus(string $id)
    {
        try{
            $user = User::findOrFail($id);

            if($user->id === 1){
                return response()->json([
                    'message' => 'No se puede modificar al usuario master.'
                ], 403);
            }
            if($user->estado === 'PENDIENTE'){
                return response()->json([
                    'message' => 'Los usuarios pendientes deben aceptar la invitación antes de cambiar su estado.'
                ], 422);
            }

            $nuevoEstado = $user->estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
            $user->update(['estado' => $nuevoEstado]);

            return response()->json([
                'message' => "Estado cambiado a {$nuevoEstado}.",
                'estado' => $nuevoEstado
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'error' => $e->getMessage()
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Funcion para reenviar invitacion
    public function resendInvitation(string $id)
    {
        try{
            $user = User::findOrFail($id);

            if($user->estado !== 'PENDIENTE'){
                return response()->json([
                    'message' => 'Solo se puede reenviar invitación a usuarios pendientes.',
                ], 422);
            }

            //Generamos nuevo token
            $token = Str::uuid()->toString() . '-' . now()->timestamp;
            $user->update([
                'invitation_token' => $token,
                'invitation_expires_at' => now()->addHours(24)
            ]);
            Mail::to($user->email)->send(new UserInvitation($user, $token));

            return response()->json([
                'message' => 'Invitación reenviada correctamente.'
            ], 200);


        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Usuario no encontrado.',
                'error' => $e->getMessage()
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Funsion para restablecer contraseña
    public function setPassword(Request $request)
    {
        $request->validate([
                'token' => 'required|string|exists:users,invitation_token',
                'password' => [
                    'required',
                    'required',
                    'min:8',
                    'confirmed',
                    'regex:/[A-Z]/',
                    'regex:/[a-z]/',
                    'regex:/[0-9]/',
                    'regex:/[@$!%*?&]/'
                ]
            ]);

        try{
            $user = User::where('invitation_token', $request->token)->firstOrFail();

            //Verificamos expiracion
            if($user->invitation_expires_at < now()){
                return response()->json([
                    'message' => 'El enlace de invitación ha expirado. Solicita uno nuevo.'
                ], 422);
            }

            //Actualizamos contraseña y limpiamos token
            $user->update([
                'password' => Hash::make($request->password),
                'invitation_token' => null,
                'invitation_expires_at' => null,
                'estado' => 'ACTIVO'
            ]);

            return response()->json([
                'message' => 'Contraseña establecida correctamente. Ya puedes iniciar sesión.'
            ], 200);

        }catch(ModelNotFoundException $e){
            return response()->json([
                'message' => 'Token inválido.',
                'error' => $e->getMessage()
            ], 404);

        }catch(\Exception $e){
            return response()->json([
                'message' => 'Error interno en el servidor.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
