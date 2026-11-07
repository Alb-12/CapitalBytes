<?php

declare(strict_types=1);


final class ValidationException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $campo)
    {
        parent::__construct($message);
    }

    public function getCampo(): string
    {
        return $this->campo;
    }
}
