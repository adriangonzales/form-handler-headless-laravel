<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when Jev answers the spam question with something other than a yes/no probability.
 */
class UnexpectedSpamClassificationException extends Exception
{
    public function __construct()
    {
        parent::__construct('Jev answered unexpectedly.');
    }
}
