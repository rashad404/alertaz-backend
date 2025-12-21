<?php

namespace App\Exceptions;

use Exception;

class TemplateRenderException extends Exception
{
    protected array $unresolvedVariables;

    public function __construct(string $message = '', array $unresolvedVariables = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->unresolvedVariables = $unresolvedVariables;
        parent::__construct($message, $code, $previous);
    }

    public function getUnresolvedVariables(): array
    {
        return $this->unresolvedVariables;
    }
}
