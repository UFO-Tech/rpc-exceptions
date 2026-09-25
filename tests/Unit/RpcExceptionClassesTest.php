<?php

declare(strict_types=1);

namespace Ufo\RpcError\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ArrayToException;
use Ufo\RpcError\IProcedureExceptionInterface;
use Ufo\RpcError\ISecurityExceptionInterface;
use Ufo\RpcError\IServerExceptionInterface;
use Ufo\RpcError\IUserInputExceptionInterface;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\CustomApplicationException;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcError\RpcInvalidBatchRequestExceptions;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcLogicException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\RpcTokenNotSentException;
use Ufo\RpcError\WrongWayException;

/**
 * Контракт конкретних класів винятків: оголошений код, дефолтне повідомлення,
 * маркерні інтерфейси та ієрархія наслідування.
 */
#[CoversClass(RpcAsyncRequestException::class)]
#[CoversClass(RpcBadParamException::class)]
#[CoversClass(RpcBadRequestException::class)]
#[CoversClass(CustomApplicationException::class)]
#[CoversClass(RpcDataNotFoundException::class)]
#[CoversClass(RpcInternalException::class)]
#[CoversClass(RpcInvalidBatchRequestExceptions::class)]
#[CoversClass(RpcInvalidTokenException::class)]
#[CoversClass(RpcJsonParseException::class)]
#[CoversClass(RpcLogicException::class)]
#[CoversClass(RpcMethodNotFoundExceptionRpc::class)]
#[CoversClass(RpcRuntimeException::class)]
#[CoversClass(RpcTokenNotSentException::class)]
#[CoversClass(WrongWayException::class)]
#[CoversClass(ArrayToException::class)]
final class RpcExceptionClassesTest extends TestCase
{
    /** @return iterable<string, array{class-string, int, string}> */
    public static function exceptionContract(): iterable
    {
        yield 'json parse'   => [RpcJsonParseException::class, -32700, 'Invalid JSON was received by the server.'];
        yield 'bad request'  => [RpcBadRequestException::class, -32600, ''];
        yield 'no method'    => [RpcMethodNotFoundExceptionRpc::class, -32601, 'Method not found'];
        yield 'bad param'    => [RpcBadParamException::class, -32602, 'Required parameter not passed'];
        yield 'internal'     => [RpcInternalException::class, -32603, 'Internal JSON-RPC error'];
        yield 'runtime'      => [RpcRuntimeException::class, -32500, 'Runtime error on procedure'];
        yield 'logic'        => [RpcLogicException::class, -32400, 'Logic error on procedure'];
        yield 'no token'     => [RpcTokenNotSentException::class, -32401, 'Unauthorized. Token not found'];
        yield 'bad token'    => [RpcInvalidTokenException::class, -32403, 'Forbidden. Invalid token'];
        yield 'no data'      => [RpcDataNotFoundException::class, -32404, 'Api method returned error "Data not found"'];
        yield 'async'        => [RpcAsyncRequestException::class, -32300, 'Async request is invalid'];
        yield 'batch'        => [RpcInvalidBatchRequestExceptions::class, -32301, 'Batch request error'];
        yield 'wrong way'    => [WrongWayException::class, -32603, ''];
    }

    #[DataProvider('exceptionContract')]
    public function testDeclaredCodeAndDefaultMessage(string $class, int $code, string $message): void
    {
        $defaults = (new \ReflectionClass($class))->getDefaultProperties();

        $this->assertSame($code, $defaults['code']);
        $this->assertSame($message, $defaults['message']);
        $this->assertSame($message, (new $class())->getMessage(), 'Порожнє повідомлення бере дефолт класу');
    }

    #[DataProvider('exceptionContract')]
    public function testEveryExceptionExtendsTheAbstractBaseAndThrowable(string $class): void
    {
        $exception = new $class();

        $this->assertInstanceOf(AbstractRpcErrorException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    #[DataProvider('exceptionContract')]
    public function testExplicitCodeAndMessageAreKept(string $class): void
    {
        $exception = new $class('explicit', -32500);

        $this->assertSame('explicit', $exception->getMessage());
        $this->assertSame(-32500, $exception->getCode());
    }

    /** @return iterable<string, array{class-string, class-string}> */
    public static function markerInterfaces(): iterable
    {
        yield 'json parse is user input'  => [RpcJsonParseException::class, IUserInputExceptionInterface::class];
        yield 'bad request is user input' => [RpcBadRequestException::class, IUserInputExceptionInterface::class];
        yield 'no method is user input'   => [RpcMethodNotFoundExceptionRpc::class, IUserInputExceptionInterface::class];
        yield 'bad param is user input'   => [RpcBadParamException::class, IUserInputExceptionInterface::class];
        yield 'async is user input'       => [RpcAsyncRequestException::class, IUserInputExceptionInterface::class];
        yield 'batch is user input'       => [RpcInvalidBatchRequestExceptions::class, IUserInputExceptionInterface::class];
        yield 'internal is server'        => [RpcInternalException::class, IServerExceptionInterface::class];
        yield 'runtime is server'         => [RpcRuntimeException::class, IServerExceptionInterface::class];
        yield 'logic is server'           => [RpcLogicException::class, IServerExceptionInterface::class];
        yield 'no data is server'         => [RpcDataNotFoundException::class, IServerExceptionInterface::class];
        yield 'no token is security'      => [RpcTokenNotSentException::class, ISecurityExceptionInterface::class];
        yield 'bad token is security'     => [RpcInvalidTokenException::class, ISecurityExceptionInterface::class];
    }

    #[DataProvider('markerInterfaces')]
    public function testMarkerInterface(string $class, string $interface): void
    {
        $this->assertInstanceOf($interface, new $class());
    }

    /** Прикладна помилка везе код домену, тому конструктор його вимагає. */
    public function testCustomApplicationExceptionDemandsItsCode(): void
    {
        $code = (new \ReflectionMethod(CustomApplicationException::class, '__construct'))->getParameters()[1];

        $this->assertSame('code', $code->getName());
        $this->assertFalse($code->isOptional(), 'код не має бути опціональним');

        $this->expectException(\ArgumentCountError::class);

        new CustomApplicationException('Limit reached');
    }

    public function testCustomApplicationExceptionKeepsTheCodeItWasGiven(): void
    {
        $exception = new CustomApplicationException('Limit reached', 4010);

        $this->assertInstanceOf(AbstractRpcErrorException::class, $exception);
        $this->assertInstanceOf(IProcedureExceptionInterface::class, $exception);
        $this->assertSame(4010, $exception->getCode());
        $this->assertSame('Limit reached', $exception->getMessage());
    }

    public function testCustomApplicationExceptionStillFallsBackToItsMessage(): void
    {
        $this->assertSame(
            'Custom application error occurred',
            (new CustomApplicationException('', 4010))->getMessage(),
        );
    }

    /** Клас без власного коду не мусить його й оголошувати. */
    public function testCustomApplicationExceptionDeclaresNoCodeOfItsOwn(): void
    {
        $this->assertSame(
            AbstractRpcErrorException::class,
            (new \ReflectionProperty(CustomApplicationException::class, 'code'))->getDeclaringClass()->getName(),
            '$code має лишитись оголошеним лише в базовому класі',
        );
    }

    public function testCategoriesDoNotOverlap(): void
    {
        $categories = [
            IUserInputExceptionInterface::class,
            IServerExceptionInterface::class,
            ISecurityExceptionInterface::class,
            IProcedureExceptionInterface::class,
        ];

        foreach (AbstractRpcErrorException::getMapping() as $class) {
            $matched = array_filter($categories, static fn (string $i): bool => is_a($class, $i, true));
            $this->assertCount(1, $matched, $class . ' має належати рівно до однієї категорії');
        }
    }

    public function testWrongWayExceptionIsNotCategorisedAndNotMapped(): void
    {
        $exception = new WrongWayException();

        $this->assertNotInstanceOf(IUserInputExceptionInterface::class, $exception);
        $this->assertNotInstanceOf(IServerExceptionInterface::class, $exception);
        $this->assertNotInstanceOf(ISecurityExceptionInterface::class, $exception);
        $this->assertNotInstanceOf(IProcedureExceptionInterface::class, $exception);
        $this->assertNotContains(WrongWayException::class, AbstractRpcErrorException::getMapping());
    }

    public function testBadRequestSubtypesHierarchy(): void
    {
        $this->assertInstanceOf(RpcBadRequestException::class, new RpcBadParamException());
        $this->assertInstanceOf(RpcBadRequestException::class, new RpcMethodNotFoundExceptionRpc());
    }

    public function testBadParamOverridesDefaultCodeConstant(): void
    {
        $this->assertSame(-32602, RpcBadParamException::DEFAULT_CODE);
        $this->assertSame(-32603, AbstractRpcErrorException::DEFAULT_CODE);
    }

    /**
     * ArrayToException — порожній клас-заглушка, який лишився в публічному API
     * пакета. Тест фіксує сам факт його існування, щоб видалення було свідомим.
     */
    public function testArrayToExceptionIsAnEmptyPlaceholder(): void
    {
        $reflection = new \ReflectionClass(ArrayToException::class);

        $this->assertSame([], $reflection->getMethods());
        $this->assertSame([], $reflection->getProperties());
        $this->assertInstanceOf(ArrayToException::class, new ArrayToException());
    }
}
