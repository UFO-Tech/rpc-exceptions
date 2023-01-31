<?php

namespace Ufo\RpcError;

class RpcInvalidTokenException extends AbstractRpcErrorException implements ISecurityExceptionInterface
{
    protected $code = -32403;
    protected $message = 'Forbidden. Invalid token';
}