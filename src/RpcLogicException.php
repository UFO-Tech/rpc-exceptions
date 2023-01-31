<?php

namespace Ufo\RpcError;

class RpcLogicException extends AbstractRpcErrorException implements IServerExceptionInterface
{
    protected $code = -32400;
    protected $message ='Logic error on procedure';
}
