<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a form entry cannot be checked for spam because no TypeSafe API key is set.
 */
class SpamClassifierNotConfiguredException extends Exception
{
    public function __construct()
    {
        parent::__construct('Jev is not configured.');
    }
}
