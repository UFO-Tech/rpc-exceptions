<?php

namespace Ufo\RpcError;

class RpcInvalidBatchRequestExceptions extends AbstractRpcErrorException implements IUserInputExceptionInterface
{
    protected $code = -32301;
    protected $message = 'Batch request error';
}