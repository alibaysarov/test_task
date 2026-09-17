<?php

namespace App\Exceptions;

use DomainException;

class InvalidReferralCodeException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The referral code is invalid.');
    }
}
