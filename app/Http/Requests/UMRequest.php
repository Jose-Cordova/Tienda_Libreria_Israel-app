<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UMRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    //Definimos si el usuario tiene permisos para hacer la solicitud
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    //Definimos las reglas de validacion que se deben cumplir
    public function rules(): array
    {
        //Obtenemos el id y extraemos la peticion
        $id = $this->route('unidadmedida');

    return [
        'nombre' => [
            'required', 'string', 'min:2', 'max:50',
            Rule::unique('unidades_medidas', 'nombre')->ignore($id)
        ],
        'equivalencia' => [
            'required', 'string', 'min:2', 'max:50',
        ],
    ];
    }
    //Definimos los mensajes para cada cosa que falle
    public function messages()
    {
        return [
        'nombre.required' => 'El nombre es obligatorio.',
        'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        'nombre.max' => 'El nombre no debe tener mas de 50 caracteres.',
        'nombre.unique' => 'Ya existe una unidad de medida con este nombre.',
        'equivalencia.required' => 'La equivalencia es obligatoria.',
        'equivalencia.min' => 'La equivalencia debe tener al menos 2 caracteres.',
        'equivalencia.max' => 'La equivalencia no debe tener mas de 50 caracteres.',

        ];
    }
    //Si la validacion falla se ejecuta la funcion
    protected function failedValidation(Validator $validator)
    {
        //Se interrumpe la ejecucion y se lanza una excepcion
        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validacion',
                'error' => $validator->errors()
            ], 422)
        );
    }
}
