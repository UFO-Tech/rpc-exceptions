<?php

namespace Ufo\RpcError;

class RpcCustomServerException extends AbstractRpcErrorException implements IServerExceptionInterface
{
    public const int DEFAULT_CODE = -32000;

    protected $code = self::DEFAULT_CODE;
    protected $message = 'Custom RPC server error';
}
