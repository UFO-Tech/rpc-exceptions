<?php

namespace Ufo\RpcError;

use JetBrains\PhpStorm\Pure;

abstract class AbstractRpcErrorException extends \Exception implements IExceptionWithData
{
    use ExceptionWithExtraDataTrait;

    const int DEFAULT_CODE = -32603;
    const int MIN_ERROR_CODE = -32768;
    const int MAX_ERROR_CODE = -32000;

    const int ROUNDING_BASE = 100;
    const int ROUNDING_MIN_CODE = -32700;

    const int NOT_SET_CODE = 0;

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
        -32000 => RpcCustomServerException::class,
    ];

    protected $code = self::DEFAULT_CODE;

    #[Pure]
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = !empty($message) ? $message : $this->message;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param \Throwable $e
     * @param bool $withPrev
     * @return static
     */
    public static function fromThrowable(\Throwable $e, bool $withPrev = true): AbstractRpcErrorException
    {
        $exception = static::fromCode($e->getCode(), $e->getMessage(), $withPrev ? $e : null);

        return $e instanceof IExceptionWithData ? $exception->changeExtraData($e->getExtraData()) : $exception;
    }

    /**
     * Дані беруться з ключа extra — власного або вкладеного в data.
     *
     * @param array $data ['code'=>-32...]
     * @return static
     */
    public static function fromArray(array $data): AbstractRpcErrorException
    {
        $exception = static::fromCode($data['code'] ?? static::DEFAULT_CODE, $data['message'] ?? '');
        $nested = $data['data'] ?? [];
        $extra = $data['extra'] ?? (is_array($nested) ? $nested['extra'] ?? [] : []);

        return is_array($extra) ? $exception->changeExtraData($extra) : $exception;
    }

    /**
     * @param string $data {"code"=>-32...}
     * @return static
     */
    public static function fromJson(string $data): AbstractRpcErrorException
    {
        $decoded = json_decode($data, true);
        return is_array($decoded) ? static::fromArray($decoded) : static::fromCode(-32700);
    }

    public static function fromCode(int $code, string $message = '', ?\Throwable $previous = null): AbstractRpcErrorException
    {
        $code = static::NOT_SET_CODE === $code ? static::DEFAULT_CODE : $code;

        $exceptionClass = match (true) {
            isset(static::ERROR_MAPPING[$code]) => static::ERROR_MAPPING[$code],
            $code < static::MIN_ERROR_CODE || $code > static::MAX_ERROR_CODE => CustomApplicationException::class,
            $code >= static::ROUNDING_MIN_CODE => static::ERROR_MAPPING[(int) (ceil($code / static::ROUNDING_BASE) * static::ROUNDING_BASE)] ?? RpcInternalException::class,
            default => RpcInternalException::class,
        };

        return new $exceptionClass($message, $code, $previous);
    }

    /**
     * @return array<int, class-string<AbstractRpcErrorException>>
     */
    public static function getMapping(): array
    {
        return static::ERROR_MAPPING;
    }

    /**
     * @return array<int, class-string<AbstractRpcErrorException>>
     */
    #[Pure]
    public static function getRpcErrorsList(): array
    {
        return static::getMapping();
    }
}
