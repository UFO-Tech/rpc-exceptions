<?php

namespace Ufo\RpcError;

class RpcDataNotFoundException extends AbstractRpcErrorException implements IServerExceptionInterface
{
    protected $code = -32404;
    protected $message = 'Api method returned error "Data not found"';
}