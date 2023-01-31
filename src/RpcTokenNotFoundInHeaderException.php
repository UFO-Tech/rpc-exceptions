<?php

namespace Ufo\RpcError;

class RpcTokenNotFoundInHeaderException extends AbstractRpcErrorException implements ISecurityExceptionInterface
{
    protected $code = -32401;
    protected $message = 'Unauthorized. Token not found';
}