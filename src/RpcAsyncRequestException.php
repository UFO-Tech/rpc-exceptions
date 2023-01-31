<?php

namespace Ufo\RpcError;

class RpcAsyncRequestException extends AbstractRpcErrorException implements IUserInputExceptionInterface
{
    protected $code = -32300;
    protected $message = 'Async request is invalid';
}
