<?php

namespace Ufo\RpcError;

class RpcInternalException extends AbstractRpcErrorException implements IServerExceptionInterface
{
    protected $code = -32603;
    protected $message ='Internal JSON-RPC error';
}
