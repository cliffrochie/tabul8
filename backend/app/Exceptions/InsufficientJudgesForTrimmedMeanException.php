<?php

namespace App\Exceptions;

use DomainException;

final class InsufficientJudgesForTrimmedMeanException extends DomainException
{
    public function __construct(int $judgeCount)
    {
        parent::__construct("Olympian Trimmed Mean requires at least 5 active judges; {$judgeCount} provided.");
    }
}
