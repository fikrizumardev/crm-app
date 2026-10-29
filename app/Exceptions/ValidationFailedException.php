<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar Service saat data input tidak lolos validasi.
 */
class ValidationFailedException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors, string $message = 'Data belum valid. Periksa kembali isian Anda.')
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
