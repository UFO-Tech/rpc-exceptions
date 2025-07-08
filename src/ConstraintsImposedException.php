<?php

namespace Ufo\RpcError;

use Throwable;

class ConstraintsImposedException extends RpcBadParamException
{
    public function __construct(
        protected  $message,
        protected array $constraintsImposed,
        protected $code = self::DEFAULT_CODE,
        protected ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getConstraintsImposed(): array
    {
        return $this->constraintsImposed;
    }
}
