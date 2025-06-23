<?php

declare(strict_types = 1);

namespace Ufo\RpcError;

class RpcCustomApplicationException extends AbstractRpcErrorException implements IProcedureExceptionInterface
{
    protected $code = -32000;
    protected $message = 'Custom application error occurred';
}