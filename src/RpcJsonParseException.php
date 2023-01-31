<?php

namespace Ufo\RpcError;

class RpcJsonParseException extends AbstractRpcErrorException implements IUserInputExceptionInterface
{
    protected $message = 'Invalid JSON was received by the server.';
    protected $code = -32700;
}
