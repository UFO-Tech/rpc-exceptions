<?php

namespace Ufo\RpcError;

use JetBrains\PhpStorm\Pure;

abstract class AbstractRpcErrorException extends \Exception
{
    const DEFAULT_CODE = -32603;

    const ERROR_MAPPING = [
        -32700 => RpcJsonParseException::class,
        -32600 => RpcBadRequestException::class,
        -32601 => RpcMethodNotFoundExceptionRpc::class,
        -32602 => RpcBadParamException::class,
        -32603 => RpcInternalException::class,
        -32500 => RpcRuntimeException::class,
        -32400 => RpcLogicException::class,
        -32401 => RpcTokenNotFoundInHeaderException::class,
        -32403 => RpcInvalidTokenException::class,
        -32300 => RpcAsyncRequestException::class,
        -32301 => RpcInvalidButchRequestExceptions::class,
        -32000 => RpcDataNotFoundException::class,
    ];

    protected $code = self::DEFAULT_CODE;

    #[Pure]
    public function __construct(string $message = "", int $code = self::DEFAULT_CODE, ?\Throwable $previous = null)
    {
        $message = (!empty($message)) ? $message : $this->message;
        parent::__construct($message, $code, $previous);
    }

    public static function fromThrowable(\Throwable $e): static
    {
        return new static(
            $e->getMessage(),
            $e->getCode(),
            $e
        );
    }

    /**
     * @param array $data ['code'=>-32...]
     * @return static
     * @throws WrongWayException
     */
    public static function fromArray(array $data): static
    {
        $code = $data['code'] ?? 0;
        if (!isset(static::ERROR_MAPPING[$code])) {
            throw new WrongWayException('Exception mapping no found');
        }
        return new (static::ERROR_MAPPING[$code])(
            $data['message'] ?? ''
        );
    }

    public static function getMapping(): array
    {
        return static::ERROR_MAPPING;
    }

    #[Pure]
    public static function getRpcErrorsList(): array
    {
        return static::getMapping();
    }
}
