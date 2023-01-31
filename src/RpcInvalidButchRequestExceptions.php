<?php

namespace Ufo\RpcError;

class RpcInvalidButchRequestExceptions extends AbstractRpcErrorException implements IUserInputExceptionInterface
{
    protected $code = -32301;
    protected $message = 'Butch request error';
}