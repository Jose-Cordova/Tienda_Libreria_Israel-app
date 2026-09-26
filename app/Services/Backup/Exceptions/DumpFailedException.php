<?php

namespace App\Services\Backup\Exceptions;

use Exception;

//Error cuando pg_dump falla o no genera un archivo válido
class DumpFailedException extends Exception
{

}
