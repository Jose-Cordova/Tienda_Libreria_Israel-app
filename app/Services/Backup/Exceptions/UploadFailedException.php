<?php

namespace App\Services\Backup\Exceptions;

use Exception;

//Error cuando rclone falla al subir, listar o borrar en la nube
class UploadFailedException extends Exception
{

}
