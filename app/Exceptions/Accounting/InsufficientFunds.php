<?php

namespace App\Exceptions\Accounting;

use DomainException;

final class InsufficientFunds extends DomainException
{
    public function __construct()
    {
        parent::__construct('Insufficient account balance.');
    }
}
