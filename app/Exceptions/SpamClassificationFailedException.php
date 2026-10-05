<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when the call asking Jev to classify a form entry for spam fails.
 */
class SpamClassificationFailedException extends Exception
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Jev error: '.$previous->getMessage(), previous: $previous);
    }
}
