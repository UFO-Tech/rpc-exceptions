<?php

declare(strict_types=1);

namespace Ufo\RpcError\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ExceptionToArrayTransformer;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\RpcInternalException;

#[CoversClass(ExceptionToArrayTransformer::class)]
final class ExceptionToArrayTransformerTest extends TestCase
{
    public function testShortInfoExposesTheClassAndItsExtraDataOnly(): void
    {
        $exception = (new RpcBadParamException('secret details'))->pushToExtraData(['field' => 'email']);

        $transformer = new ExceptionToArrayTransformer($exception, 'dev');

        $this->assertSame(
            ['exception' => RpcBadParamException::class, 'extra' => ['field' => 'email']],
            $transformer->getShortInfo(),
        );
    }

    /** Чужий виняток не несе extra взагалі — йому нізвідки його взяти. */
    public function testShortInfoOfForeignThrowableHasNoExtraKey(): void
    {
        $transformer = new ExceptionToArrayTransformer(new \RuntimeException('boom'), 'dev');

        $this->assertSame(['exception' => \RuntimeException::class], $transformer->getShortInfo());
    }

    public function testFullInfoStructure(): void
    {
        $exception = new RpcDataNotFoundException('nothing here', -32404);
        $transformer = new ExceptionToArrayTransformer($exception, 'dev');

        $info = $transformer->getFullInfo();

        $this->assertSame(
            ['exception', 'extra', 'message', 'code', 'file', 'line', 'trace', 'trace_string', 'previous'],
            array_keys($info)
        );
        $this->assertSame(RpcDataNotFoundException::class, $info['exception']);
        $this->assertSame('nothing here', $info['message']);
        $this->assertSame(-32404, $info['code']);
        $this->assertSame($exception->getFile(), $info['file']);
        $this->assertSame($exception->getLine(), $info['line']);
        $this->assertSame($exception->getTrace(), $info['trace']);
        $this->assertSame($exception->getTraceAsString(), $info['trace_string']);
        $this->assertNull($info['previous']);
    }

    public function testRpcExceptionKeepsItsOwnCode(): void
    {
        $transformer = new ExceptionToArrayTransformer(new RpcBadParamException('m', -32602), 'dev');

        $this->assertSame(-32602, $transformer->getFullInfo()['code']);
    }

    /** Придатний код чужого винятку доходить до повного дампа незміненим. */
    public function testNonRpcExceptionKeepsItsOwnCode(): void
    {
        $transformer = new ExceptionToArrayTransformer(new \RuntimeException('boom', 42), 'dev');

        $info = $transformer->getFullInfo();

        $this->assertSame(42, $info['code']);
        $this->assertSame(\RuntimeException::class, $info['exception']);
        $this->assertSame('boom', $info['message']);
    }

    /** Дамп і фабрика мають звати одне й те саме число. */
    public function testDumpedCodeMatchesFromThrowable(): void
    {
        $exception = new \RuntimeException('boom', 500);

        $this->assertSame(
            AbstractRpcErrorException::fromThrowable($exception)->getCode(),
            (new ExceptionToArrayTransformer($exception, 'dev'))->getFullInfo()['code'],
        );
    }

    public function testCodelessExceptionFallsBackToDefaultCode(): void
    {
        $transformer = new ExceptionToArrayTransformer(new \RuntimeException('boom'), 'dev');

        $this->assertSame(AbstractRpcErrorException::DEFAULT_CODE, $transformer->getFullInfo()['code']);
    }

    /** @return iterable<string, array{mixed, int}> */
    public static function foreignCodes(): iterable
    {
        yield 'int'                => [500, 500];
        yield 'negative int'       => [-32602, -32602];
        yield 'numeric string'     => ['23000', 23000];
        yield 'leading zeroes'     => ['08006', 8006];
        yield 'float without loss' => [500.0, 500];
        yield 'zero'               => [0, AbstractRpcErrorException::DEFAULT_CODE];
        yield 'zero string'        => ['00000', AbstractRpcErrorException::DEFAULT_CODE];
        yield 'sqlstate'           => ['HY000', AbstractRpcErrorException::DEFAULT_CODE];
        yield 'empty string'       => ['', AbstractRpcErrorException::DEFAULT_CODE];
        yield 'float'              => [1.5, 1];
        yield 'numeric string float' => ['1.5', 1];
    }

    /**
     * Трансформер кличуть в обробнику помилки, тому він не має падати ні на якому коді.
     */
    #[DataProvider('foreignCodes')]
    public function testForeignCodeIsKeptOnlyWhenItIsUsable(mixed $code, int $expected): void
    {
        $exception = new class('db down', $code) extends \Exception {
            public function __construct(string $message, mixed $code)
            {
                parent::__construct($message);
                $this->code = $code;
            }
        };

        $this->assertSame($expected, (new ExceptionToArrayTransformer($exception, 'dev'))->getFullInfo()['code']);
    }

    /** Стара інтенція «код не витікає назовні» тримається на середовищі, а не на підміні числа. */
    public function testNonDevEnvironmentHidesTheCodeEntirely(): void
    {
        $transformer = new ExceptionToArrayTransformer(new \RuntimeException('boom', 500), 'prod');

        $this->assertArrayNotHasKey('code', $transformer->infoByEnvironment());
    }

    public function testDevEnvironmentReturnsFullInfo(): void
    {
        $transformer = new ExceptionToArrayTransformer(new RpcInternalException('oops'), 'dev');

        $this->assertSame($transformer->getFullInfo(), $transformer->infoByEnvironment());
    }

    /** @return iterable<string, array{string}> */
    public static function nonDevEnvironments(): iterable
    {
        yield 'prod'    => ['prod'];
        yield 'staging' => ['staging'];
        yield 'empty'   => [''];
        yield 'DEV uppercase is not dev' => ['DEV'];
    }

    #[DataProvider('nonDevEnvironments')]
    public function testNonDevEnvironmentReturnsShortInfoOnly(string $env): void
    {
        $transformer = new ExceptionToArrayTransformer(new RpcInternalException('oops'), $env);

        $this->assertSame(
            ['exception' => RpcInternalException::class, 'extra' => []],
            $transformer->infoByEnvironment(),
        );
    }

    /** Середовище test бачить те саме, що й dev: трейс, файл і рядок. */
    public function testTestEnvironmentAlsoReturnsFullInfo(): void
    {
        $transformer = new ExceptionToArrayTransformer(new RpcInternalException('oops'), 'test');

        $this->assertSame($transformer->getFullInfo(), $transformer->infoByEnvironment());
    }

    public function testPreviousIsNullWhenExceptionHasNoPrevious(): void
    {
        $transformer = new ExceptionToArrayTransformer(new RpcInternalException('alone'), 'dev');

        $this->assertNull($transformer->getPrevious());
    }

    public function testPreviousIsUnwrappedInDevEnvironment(): void
    {
        $root = new \LogicException('root cause');
        $exception = new RpcInternalException('wrapper', -32603, $root);

        $previous = (new ExceptionToArrayTransformer($exception, 'dev'))->getPrevious();

        $this->assertIsArray($previous);
        $this->assertSame(\LogicException::class, $previous['exception']);
        $this->assertSame('root cause', $previous['message']);
        $this->assertSame(AbstractRpcErrorException::DEFAULT_CODE, $previous['code']);
        $this->assertNull($previous['previous']);
    }

    public function testPreviousIsShortenedInNonDevEnvironment(): void
    {
        $exception = new RpcInternalException('wrapper', -32603, new \LogicException('root cause'));

        $previous = (new ExceptionToArrayTransformer($exception, 'prod'))->getPrevious();

        $this->assertSame(['exception' => \LogicException::class], $previous);
    }

    public function testWholeChainIsUnwrappedRecursively(): void
    {
        $level3 = new \LogicException('level 3');
        $level2 = new RpcBadParamException('level 2', -32602, $level3);
        $level1 = new RpcInternalException('level 1', -32603, $level2);

        $info = (new ExceptionToArrayTransformer($level1, 'dev'))->getFullInfo();

        $this->assertSame('level 1', $info['message']);
        $this->assertSame('level 2', $info['previous']['message']);
        $this->assertSame(-32602, $info['previous']['code']);
        $this->assertSame('level 3', $info['previous']['previous']['message']);
        $this->assertNull($info['previous']['previous']['previous']);
    }

    public function testPreviousIsMemoisedAndStable(): void
    {
        $transformer = new ExceptionToArrayTransformer(
            new RpcInternalException('wrapper', -32603, new \LogicException('root cause')),
            'dev'
        );

        $this->assertSame($transformer->getPrevious(), $transformer->getPrevious());
    }
}
