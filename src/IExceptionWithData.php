<?php

namespace Ufo\RpcError;

interface IExceptionWithData
{
    /**
     * Returns an array of arbitrarily nested arrays whose leaf values
     * are strings, integers, floats, booleans, or null.
     *
     * @return array<int|string, mixed>
     */
    public function getExtraData(): array;

    /**
     * Values that cannot travel in an error response are dropped.
     *
     * @param array<int|string, mixed> $data
     * @return static
     */
    public function changeExtraData(array $data): static;

    /**
     * Values that cannot travel in an error response are dropped.
     *
     * @param array<int|string, mixed> $data
     * @return static
     */
    public function pushToExtraData(array $data): static;
}