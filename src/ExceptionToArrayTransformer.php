<?php

namespace Ufo\RpcError;

class ExceptionToArrayTransformer
{
    protected ?ExceptionToArrayTransformer $previous = null;

    public function __construct(protected \Throwable $e, protected string $env)
    {
    }

    protected function getCode(): int
    {
        $code = $this->e->getCode();
        $code = is_numeric($code) ? (int) $code : AbstractRpcErrorException::NOT_SET_CODE;
        return AbstractRpcErrorException::NOT_SET_CODE === $code ? AbstractRpcErrorException::DEFAULT_CODE : $code;
    }

    public function getFullInfo(): array
    {
        return $this->getShortInfo()
            + [
            'message' => $this->e->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->e->getFile(),
            'line' => $this->e->getLine(),
            'trace' => $this->e->getTrace(),
            'trace_string' => $this->e->getTraceAsString(),
            'previous' => $this->getPrevious(),
        ];
    }

    /**
     * @return ?array
     */
    public function getPrevious(): ?array
    {
        $data = null;
        try {
            $data = $this->previous->infoByEnvironment();
        } catch (\Throwable) {
            if (is_null($this->previous) && !is_null($this->e->getPrevious())) {
                $this->previous = new static($this->e->getPrevious(), $this->env);
                $data = $this->getPrevious();
            }
        }
        return $data;
    }

    public function getShortInfo(): array
    {
        $data = [
            'exception' => $this->e::class,
        ];

        if ($this->e instanceof IExceptionWithData && !empty($this->e->getExtraData())) {
            $data = array_merge($data, ['extra' => $this->e->getExtraData()]);
        }

        return $data;
    }

    public function infoByEnvironment(): array
    {
        return match ($this->env) {
            'dev', 'test' =>  $this->getFullInfo(),
            default =>  $this->getShortInfo(),
        };
    }
}
