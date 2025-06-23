<?php

namespace Ufo\RpcError;

use JetBrains\PhpStorm\Pure;

abstract class AbstractRpcErrorException extends \Exception
{
    const int DEFAULT_CODE = -32603;
    const int MIN_ERROR_CODE = -32799;
    const int MAX_ERROR_CODE = -32000;
    const int ROUNDING_BASE = 100;

    const array ERROR_MAPPING = [
        -32700 => RpcJsonParseException::class,
        -32600 => RpcBadRequestException::class,
        -32601 => RpcMethodNotFoundExceptionRpc::class,
        -32602 => RpcBadParamException::class,
        -32603 => RpcInternalException::class,
        -32500 => RpcRuntimeException::class,
        -32400 => RpcLogicException::class,
        -32401 => RpcTokenNotSentException::class,
        -32403 => RpcInvalidTokenException::class,
        -32404 => RpcDataNotFoundException::class,
        -32300 => RpcAsyncRequestException::class,
        -32301 => RpcInvalidBatchRequestExceptions::class,
        -32000 => RpcCustomApplicationException::class,
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
     * @param bool $withPrev
     * @return static
     */
    public static function fromThrowable(\Throwable $e, bool $withPrev = true): AbstractRpcErrorException
    {
        $class = static::ERROR_MAPPING[$e->getCode()] ?? RpcRuntimeException::class;
        return new $class(
            $e->getMessage(),
            $e->getCode(),
            $withPrev ? $e : null
        );
    }

    /**
     * @param array $data ['code'=>-32...]
     * @return static
     * @throws RpcRuntimeException
     */
    public static function fromArray(array $data): AbstractRpcErrorException
    {
        return static::fromCode($data['code'] ?? 0, $data['message'] ?? '');
    }

    /**
     * @param string $data {"code"=>-32...}
     * @return static
     * @throws RpcRuntimeException
     */
    public static function fromJson(string $data): AbstractRpcErrorException
    {
        return static::fromArray(json_decode($data, true));
    }

    /**
     * @param int $code
     * @param string $message
     * @return static
     * @throws RpcRuntimeException
     */
    public static function fromCode(int $code, string $message = ''): AbstractRpcErrorException
    {
        if ($code < static::MIN_ERROR_CODE || $code > static::MAX_ERROR_CODE) {
            throw static::createFallbackException($code, $message);
        }

        $exceptionClass = static::ERROR_MAPPING[$code]
            ?? static::ERROR_MAPPING[(int) (ceil($code / static::ROUNDING_BASE) * static::ROUNDING_BASE)]
            ?? null;

        if ($exceptionClass === null) {
            throw static::createFallbackException($code, $message);
        }

        return new $exceptionClass($message, $code);
    }

    protected static function createFallbackException(int $code, string $message): RpcRuntimeException
    {
        return new RpcRuntimeException(sprintf('EMNF (%d): %s', $code, $message), $code);
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
