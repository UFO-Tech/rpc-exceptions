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
        -32301 => RpcInvalidBatchRequestExceptions::class,
        -32000 => RpcDataNotFoundException::class,
    ];

    protected $code = self::DEFAULT_CODE;

    #[Pure]
    public function __construct(string $message = "", int $code = self::DEFAULT_CODE, ?\Throwable $previous = null)
    {
        $message = (!empty($message)) ? $message : $this->message;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param \Throwable $e
     * @return static
     */
    public static function fromThrowable(\Throwable $e): AbstractRpcErrorException
    {
        $class = static::ERROR_MAPPING[$e->getCode()] ?? RpcRuntimeException::class;
        return new $class(
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
    public static function fromArray(array $data): AbstractRpcErrorException
    {
        return static::fromCode($data['code'] ?? 0, $data['message'] ?? '');
    }

    /**
     * @param string $data {"code"=>-32...}
     * @return static
     * @throws WrongWayException
     */
    public static function fromJson(string $data): AbstractRpcErrorException
    {
        return static::fromArray(json_decode($data, true));
    }

    /**
     * @param int $code
     * @param string $message
     * @return static
     * @throws WrongWayException
     */
    public static function fromCode(int $code, string $message = ''): AbstractRpcErrorException
    {
        if (!isset(static::ERROR_MAPPING[$code])) {
            throw new WrongWayException('Exception mapping no found');
        }
        return new (static::ERROR_MAPPING[$code])($message);
    }

    /**
     * @return string[]
     */
    public static function getMapping(): array
    {
        return static::ERROR_MAPPING;
    }

    /**
     * @return string[]
     */
    #[Pure]
    public static function getRpcErrorsList(): array
    {
        return static::getMapping();
    }
}
