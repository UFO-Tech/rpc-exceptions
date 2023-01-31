<?php

namespace Ufo\RpcError;

class RpcBadParamException extends RpcBadRequestException implements IUserInputExceptionInterface
{
    protected $code = -32602;
    protected $message = 'Required parameter not passed';
}
