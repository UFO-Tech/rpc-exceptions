<?php

namespace Ufo\RpcError;

class RpcBadParamException extends RpcBadRequestException implements IUserInputExceptionInterface
{
    public const int DEFAULT_CODE = -32602;
    protected $code = self::DEFAULT_CODE;
    protected $message = 'Required parameter not passed';
}
