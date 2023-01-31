<?php

namespace Ufo\RpcError;

class RpcRuntimeException extends AbstractRpcErrorException implements IServerExceptionInterface
{
    protected $code = -32500;
    protected $message ='Runtime error on procedure';
}
