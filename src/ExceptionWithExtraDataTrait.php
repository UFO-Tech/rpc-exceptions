<?php

namespace Ufo\RpcError;

trait ExceptionWithExtraDataTrait
{
    /**
     * @var array<int|string, mixed>
     */
    private array $extraData = [];

    /**
     * @return array<int|string, mixed>
     */
    public function getExtraData(): array
    {
        return $this->extraData;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function changeExtraData(array $data): static
    {
        $this->extraData = $this->portableExtraOnly($data);

        return $this;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function pushToExtraData(array $data): static
    {
        $this->extraData = array_merge($this->extraData, $this->portableExtraOnly($data));

        return $this;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    protected function portableExtraOnly(array $data): array
    {
        $kept = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $kept[$key] = $this->portableExtraOnly($value);

                continue;
            }

            if ($this->isValidExtraValue($value)) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    protected function isValidExtraValue(mixed $value): bool
    {
        return match (true) {
            null === $value, is_bool($value), is_int($value), is_float($value), is_string($value) => true,
            default => false,
        };
    }
}
