<?php

namespace Ufo\RpcError;

class RpcBadRequestException extends AbstractRpcErrorException implements IUserInputExceptionInterface
{
    protected $code = -32600;
    protected $message = '';
}