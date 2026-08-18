<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ya usamos middleware JWT
    }

    // Convierte strings vacíos a null para evitar problemas de validación
    protected function prepareForValidation()
    {
        $this->merge([
            'dui'      => $this->dui === '' ? null : $this->dui,
            'telefono' => $this->telefono === '' ? null : $this->telefono,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre'   => 'required|string|max:50',
            'dui'      => 'nullable|string|max:10',
            'telefono' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max'      => 'El nombre no debe exceder los 50 caracteres.',
            'dui.max'         => 'El DUI no debe exceder los 10 caracteres.',
            'telefono.max'    => 'El teléfono no debe exceder los 20 caracteres.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dui = $this->input('dui');

            // Validar DUI solo si se envió un valor
            if ($dui && !$this->validarDui($dui)) {
                $validator->errors()->add(
                    'dui',
                    'El DUI ingresado (' . $dui . ') no es válido. Por favor, verifícalo.'
                );
            }
        });
    }

    private function validarDui($dui)
    {
        if (!preg_match('/^\d{8}-\d{1}$/', $dui)) {
            return false;
        }

        $digitos = str_split(str_replace('-', '', $dui));
        $factores = [9, 8, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += (int)$digitos[$i] * $factores[$i];
        }

        $residuo = $suma % 10;
        $digitoVerificador = (10 - $residuo) % 10;

        return (int)$digitos[8] === $digitoVerificador;
    }
}
