<?php

namespace App\Services\Backup\Exceptions;

use Exception;

//Error cuando openssl falla o no genera un archivo cifrado válido.
class EncryptionFailedException extends Exception
{
    
}
