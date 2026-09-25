<?php

declare(strict_types = 1);

namespace Ufo\RpcError;

class CustomApplicationException extends AbstractRpcErrorException implements IProcedureExceptionInterface
{
    protected $message = 'Custom application error occurred';

    public function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}