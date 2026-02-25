<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InvalidCsvHeaderException extends UnprocessableEntityHttpException
{
    public function __construct(string $message = 'Invalid CSV header.')
    {
        parent::__construct($message);
    }
}
