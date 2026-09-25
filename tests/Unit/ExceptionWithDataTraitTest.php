<?php

declare(strict_types=1);

namespace Ufo\RpcError\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcError\ExceptionWithExtraDataTrait;
use Ufo\RpcError\IExceptionWithData;
use Ufo\RpcError\RpcBadParamException;

/**
 * Контракт даних винятку: у extraData лишається тільки те, що переживе json_encode.
 */
#[CoversTrait(ExceptionWithExtraDataTrait::class)]
final class ExceptionWithDataTraitTest extends TestCase
{
    /** Трейт мусить закривати інтерфейс цілком — інакше клас із ним не скомпілюється. */
    private static function holder(): IExceptionWithData
    {
        return new class('param missing') extends RpcBadParamException implements IExceptionWithData {
            use ExceptionWithExtraDataTrait;
        };
    }

    public function testDataIsEmptyUntilSomethingIsPutIn(): void
    {
        $this->assertSame([], self::holder()->getExtraData());
    }

    /** @return iterable<string, array{mixed}> */
    public static function portableLeaves(): iterable
    {
        yield 'int'          => [42];
        yield 'negative int' => [-32602];
        yield 'zero'         => [0];
        yield 'float'        => [1.5];
        yield 'string'       => ['email'];
        yield 'utf-8 string' => ['поле «емейл»'];
        yield 'empty string' => [''];
        yield 'true'         => [true];
        yield 'false'        => [false];
        yield 'null'         => [null];
        yield 'empty array'  => [[]];
    }

    #[DataProvider('portableLeaves')]
    public function testPortableLeafSurvives(mixed $leaf): void
    {
        $data = self::holder()->changeExtraData(['field' => $leaf])->getExtraData();

        $this->assertSame(['field' => $leaf], $data);
    }

    /** @return iterable<string, array{mixed}> */
    public static function unportableLeaves(): iterable
    {
        yield 'object'    => [new \stdClass()];
        yield 'exception' => [new \RuntimeException('boom')];
        yield 'closure'   => [static fn (): int => 1];
    }

    #[DataProvider('unportableLeaves')]
    public function testUnportableLeafIsDropped(mixed $leaf): void
    {
        $data = self::holder()->changeExtraData(['field' => $leaf, 'kept' => 1])->getExtraData();

        $this->assertSame(['kept' => 1], $data);
    }

    public function testResourceIsDropped(): void
    {
        $handle = fopen('php://memory', 'rb');

        $data = self::holder()->changeExtraData(['stream' => $handle, 'kept' => 1])->getExtraData();

        fclose($handle);
        $this->assertSame(['kept' => 1], $data);
    }

    public function testNestingIsKeptAndFilteredAtEveryLevel(): void
    {
        $data = self::holder()->changeExtraData([
            'user' => [
                'id' => 7,
                'avatar' => new \stdClass(),
                'roles' => ['admin', 'editor'],
                'meta' => ['score' => 1.5, 'broken' => new \stdClass()],
            ],
        ])->getExtraData();

        $this->assertSame([
            'user' => [
                'id' => 7,
                'roles' => ['admin', 'editor'],
                'meta' => ['score' => 1.5],
            ],
        ], $data);
    }

    /** Глибока вкладеність копіюється цілком — межі глибини немає. */
    public function testDeepNestingIsKeptWhole(): void
    {
        $deep = 'bottom';
        for ($i = 0; $i < 30; $i++) {
            $deep = ['down' => $deep];
        }

        $data = self::holder()->changeExtraData(['deep' => $deep, 'kept' => 1])->getExtraData();

        $leaf = $data['deep'];
        while (is_array($leaf)) {
            $leaf = $leaf['down'];
        }

        $this->assertSame('bottom', $leaf);
        $this->assertSame(1, $data['kept']);
    }

    public function testOnlyValuesThatCanTravelSurvive(): void
    {
        $handle = fopen('php://memory', 'rb');

        $kept = self::holder()->changeExtraData([
            'object' => new \stdClass(),
            'closure' => static fn (): int => 1,
            'stream' => $handle,
            'nested' => ['ok' => 'value', 'bad' => new \stdClass()],
            'ok' => [1, 2.5, 'three', true, null],
        ])->getExtraData();

        fclose($handle);

        $this->assertSame(['nested' => ['ok' => 'value'], 'ok' => [1, 2.5, 'three', true, null]], $kept);
        $this->assertIsString(json_encode($kept));
    }

    /**
     * Фільтр дивиться на тип, а не на зміст — це його межа, зафіксована навмисно.
     *
     * NAN, INF і зіпсований UTF-8 є float і string, тому вони проходять і ламають json_encode
     * усієї відповіді. Той, хто кладе дані, відповідає за скінченні числа й валідні рядки.
     */
    public function testNonFiniteNumbersAndBrokenStringsAreNotFiltered(): void
    {
        $kept = self::holder()->changeExtraData(['ratio' => NAN, 'limit' => INF, 'name' => "\xB1\x31"])->getExtraData();

        $this->assertNan($kept['ratio']);
        $this->assertInfinite($kept['limit']);
        $this->assertSame("\xB1\x31", $kept['name']);
        $this->assertFalse(json_encode($kept), 'такий дамп не закодується');
    }

    public function testChangeDataReplaces(): void
    {
        $holder = self::holder()->changeExtraData(['first' => 1]);

        $this->assertSame(['second' => 2], $holder->changeExtraData(['second' => 2])->getExtraData());
    }

    public function testPushToDataMerges(): void
    {
        $holder = self::holder()->changeExtraData(['first' => 1]);

        $this->assertSame(['first' => 1, 'second' => 2], $holder->pushToExtraData(['second' => 2])->getExtraData());
    }

    public function testPushToDataFiltersToo(): void
    {
        $holder = self::holder()->pushToExtraData(['object' => new \stdClass(), 'kept' => 'yes']);

        $this->assertSame(['kept' => 'yes'], $holder->getExtraData());
    }

    public function testSettersAreFluent(): void
    {
        $holder = self::holder();

        $this->assertSame($holder, $holder->changeExtraData(['a' => 1]));
        $this->assertSame($holder, $holder->pushToExtraData(['b' => 2]));
    }
}
