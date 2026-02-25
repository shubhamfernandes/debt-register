<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvalidCsvFileException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = 'Invalid CSV file.')
    {
        parent::__construct($message);
    }
}
