<?php

namespace Ufo\RpcError;

class RpcDataNotFoundException extends AbstractRpcErrorException implements IProcedureExceptionInterface
{
    protected $code = -32000;
    protected $message = 'Api method returned error "Data not found"';
}