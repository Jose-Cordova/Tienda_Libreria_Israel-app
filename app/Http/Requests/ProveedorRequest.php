<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProveedorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('ADMIN');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    //Funcion que define las reglas de validacion
    public function rules(): array
    {
        //Obtenemos el id y extraemos la preticion
        $id = $this->route('proveedore');

        return [
            'nombre' => 'required|string|min:2|max:50',
            'telefono' => 'required|string|min:8|max:20',
            'email' => ['required', 'email', 'max:100', Rule::unique('proveedores', 'email')->ignore($id)],
            'direccion' => 'string|min:5|max:250'
        ];
    }
    //Definimos los mensajes por si no se cumplen las validaciones
    public function messages()
    {
        return [
            'nombre.required'    => 'El nombre es obligatorio.',
            'nombre.min'         => 'El nombre debe tener al menos 2 caracteres.',
            'telefono.required'  => 'El teléfono es obligatorio.',
            'telefono.min'       => 'El teléfono debe tener al menos 8 caracteres.',
            'email.required'     => 'El correo es obligatorio.',
            'email.email'        => 'El correo no tiene un formato válido.',
            'email.unique'       => 'Ya existe un proveedor con este correo.',
            'direccion.min'      => 'La dirección debe tener al menos 5 caracteres.'
        ];
    }
    //Si la validacion falla se ejecuta esta funcion
    protected function failedValidation(Validator $validator)
    {
        //Se interrumpe la ejecucion y se lanza una exepcion
        throw new HttpResponseException(
            response()->json([
                'message' => 'Erro de validacion.',
                'error' => $validator->errors()
            ], 422)
        );
    }
}
