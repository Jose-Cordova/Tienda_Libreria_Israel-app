<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CMMPRequest extends FormRequest
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
        $id = $this->route('categoria') ?? $this->route('marca');
        $isCategoria = str_contains($this->path(), 'categorias');
        $tabla = $isCategoria ? 'categorias' : 'marcas';
        $regex = $isCategoria ? '/^[\pL\s]+$/u' : '/^[\pL\pN\s\-]+$/u';

        return [
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:50',
                "regex:{$regex}",
                Rule::unique($tabla, 'nombre')->ignore($id)
            ],
            'seccion' => [
                'required',
                'in:TIENDA,LIBRERIA,MEDICAMENTO'
            ]
        ];
    }

    public function messages()
    {
        $isCategoria = str_contains($this->path(), 'categorias');
        $tipo = $isCategoria ? 'categoría' : 'marca';
        $regexMsg = $isCategoria
            ? "El nombre de la categoría solo debe contener letras."
            : "El nombre de la marca contiene caracteres no permitidos.";

        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max' => 'El nombre no debe superar los 50 caracteres.',
            'nombre.regex' => $regexMsg,
            'nombre.unique' => "Ya existe una {$tipo} con este nombre.",
            'seccion.required' => 'La sección es obligatoria.',
            'seccion.in' => 'La sección debe de ser TIENDA, LIBRERIA o MEDICAMENTO.'
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
