<?php

namespace App\Exceptions;

use RuntimeException;

final class ImmutableAuditLogException extends RuntimeException
{
    public function __construct(string $message = 'Score audit logs are append-only and cannot be modified or deleted.')
    {
        parent::__construct($message);
    }
}
