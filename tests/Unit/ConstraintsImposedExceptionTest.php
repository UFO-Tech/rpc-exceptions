<?php

declare(strict_types=1);

namespace Ufo\RpcError\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcError\IUserInputExceptionInterface;
use Ufo\RpcError\RpcBadParamException;

#[CoversClass(ConstraintsImposedException::class)]
final class ConstraintsImposedExceptionTest extends TestCase
{
    private const array CONSTRAINTS = [
        'email' => ['NotBlank', 'Email'],
        'age' => ['Positive'],
    ];

    public function testCarriesImposedConstraints(): void
    {
        $exception = new ConstraintsImposedException('validation failed', self::CONSTRAINTS);

        $this->assertSame(self::CONSTRAINTS, $exception->getConstraintsImposed());
        $this->assertSame('validation failed', $exception->getMessage());
    }

    public function testEmptyConstraintsAreAllowed(): void
    {
        $this->assertSame([], (new ConstraintsImposedException('none', []))->getConstraintsImposed());
    }

    /**
     * Власний конструктор має self::DEFAULT_CODE, де self резолвиться
     * в ConstraintsImposedException і успадковує RpcBadParamException::DEFAULT_CODE.
     */
    public function testDefaultCodeIsInvalidParams(): void
    {
        $this->assertSame(-32602, (new ConstraintsImposedException('m', []))->getCode());
        $this->assertSame(RpcBadParamException::DEFAULT_CODE, (new ConstraintsImposedException('m', []))->getCode());
    }

    public function testExplicitCodeAndPreviousAreKept(): void
    {
        $previous = new \RuntimeException('root cause');

        $exception = new ConstraintsImposedException('m', self::CONSTRAINTS, -32600, $previous);

        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testIsATypeOfBadParamException(): void
    {
        $exception = new ConstraintsImposedException('m', []);

        $this->assertInstanceOf(RpcBadParamException::class, $exception);
        $this->assertInstanceOf(AbstractRpcErrorException::class, $exception);
        $this->assertInstanceOf(IUserInputExceptionInterface::class, $exception);
    }

    /**
     * ConstraintsImposedException немає в ERROR_MAPPING, тому фабрики
     * повертають базовий RpcBadParamException і втрачають constraints.
     */
    public function testFactoriesDoNotRestoreConstraints(): void
    {
        $origin = new ConstraintsImposedException('validation failed', self::CONSTRAINTS);

        $restored = AbstractRpcErrorException::fromThrowable($origin);

        $this->assertInstanceOf(RpcBadParamException::class, $restored);
        $this->assertNotInstanceOf(ConstraintsImposedException::class, $restored);
        $this->assertSame($origin, $restored->getPrevious());
    }

    public function testIsCatchableAsBadParamException(): void
    {
        try {
            throw new ConstraintsImposedException('validation failed', self::CONSTRAINTS);
        } catch (RpcBadParamException $e) {
            $this->assertInstanceOf(ConstraintsImposedException::class, $e);
            $this->assertSame(self::CONSTRAINTS, $e->getConstraintsImposed());

            return;
        }
    }
}
