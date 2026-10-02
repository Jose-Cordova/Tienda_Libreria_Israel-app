<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

//Expresión regular única para correos electrónicos (la misma que src/utils/validaciones.js en el frontend)
class CorreoValido implements ValidationRule
{
    public const REGEX = '/^[A-Za-z0-9]+([._%+-][A-Za-z0-9]+)*@[A-Za-z0-9]+(-[A-Za-z0-9]+)*(\.[A-Za-z0-9]+(-[A-Za-z0-9]+)*)*\.[A-Za-z]{2,}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(! is_string($value) || ! preg_match(self::REGEX, $value)){
            $fail('El correo electrónico no es válido. Ej: nombre@dominio.com');
        }
    }
}
