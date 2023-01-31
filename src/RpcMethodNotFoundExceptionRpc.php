<?php

namespace Ufo\RpcError;

class RpcMethodNotFoundExceptionRpc extends RpcBadRequestException implements IUserInputExceptionInterface
{
    protected $code = -32601;
    protected $message = 'Method not found';
}
