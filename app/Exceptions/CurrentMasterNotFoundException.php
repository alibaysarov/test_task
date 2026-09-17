<?php

namespace App\Exceptions;

use RuntimeException;

class CurrentMasterNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Current master not found.');
    }
}
