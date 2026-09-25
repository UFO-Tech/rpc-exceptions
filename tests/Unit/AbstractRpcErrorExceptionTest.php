<?php

declare(strict_types=1);

namespace Ufo\RpcError\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\ExceptionToArrayTransformer;
use Ufo\RpcError\RpcAsyncRequestException;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcError\RpcBadRequestException;
use Ufo\RpcError\ConstraintsImposedException;
use Ufo\RpcError\CustomApplicationException;
use Ufo\RpcError\RpcCustomServerException;
use Ufo\RpcError\WrongWayException;
use Ufo\RpcError\RpcDataNotFoundException;
use Ufo\RpcError\RpcInternalException;
use Ufo\RpcError\RpcInvalidBatchRequestExceptions;
use Ufo\RpcError\RpcInvalidTokenException;
use Ufo\RpcError\RpcJsonParseException;
use Ufo\RpcError\RpcLogicException;
use Ufo\RpcError\RpcMethodNotFoundExceptionRpc;
use Ufo\RpcError\RpcRuntimeException;
use Ufo\RpcError\RpcTokenNotSentException;

#[CoversClass(AbstractRpcErrorException::class)]
final class AbstractRpcErrorExceptionTest extends TestCase
{
    /** Коди, для яких у ERROR_MAPPING є точний запис. */
    public static function mappedCodes(): iterable
    {
        yield 'parse error'          => [-32700, RpcJsonParseException::class];
        yield 'invalid request'      => [-32600, RpcBadRequestException::class];
        yield 'method not found'     => [-32601, RpcMethodNotFoundExceptionRpc::class];
        yield 'invalid params'       => [-32602, RpcBadParamException::class];
        yield 'internal error'       => [-32603, RpcInternalException::class];
        yield 'runtime'              => [-32500, RpcRuntimeException::class];
        yield 'logic'                => [-32400, RpcLogicException::class];
        yield 'token not sent'       => [-32401, RpcTokenNotSentException::class];
        yield 'invalid token'        => [-32403, RpcInvalidTokenException::class];
        yield 'data not found'       => [-32404, RpcDataNotFoundException::class];
        yield 'async request'        => [-32300, RpcAsyncRequestException::class];
        yield 'invalid batch'        => [-32301, RpcInvalidBatchRequestExceptions::class];
    }

    // ---------------------------------------------------------------- fromCode

    #[DataProvider('mappedCodes')]
    public function testFromCodeMapsToDedicatedException(int $code, string $expected): void
    {
        $exception = AbstractRpcErrorException::fromCode($code, 'boom');

        $this->assertInstanceOf($expected, $exception);
        $this->assertSame($code, $exception->getCode());
        $this->assertSame('boom', $exception->getMessage());
    }

    #[DataProvider('mappedCodes')]
    public function testFromCodeWithoutMessageUsesClassDefaultMessage(int $code, string $expected): void
    {
        $exception = AbstractRpcErrorException::fromCode($code);

        $this->assertInstanceOf($expected, $exception);
        $this->assertSame((new \ReflectionClass($expected))->getDefaultProperties()['message'], $exception->getMessage());
    }

    /**
     * Код без власного запису підбирає клас своєї сотні: коди всередині
     * діапазону живуть родинами (-324xx — логіка, -323xx — асинхронність).
     *
     * ceil() тягне відʼємні вгору, тому -32499 потрапляє в -32400, а не -32500.
     */
    public static function roundedCodes(): iterable
    {
        yield 'top of bad request bucket' => [-32699, RpcBadRequestException::class];
        yield 'mid bad request bucket'    => [-32650, RpcBadRequestException::class];
        yield 'top of logic bucket'       => [-32499, RpcLogicException::class];
        yield 'mid logic bucket'          => [-32450, RpcLogicException::class];
        yield 'runtime bucket'            => [-32550, RpcRuntimeException::class];
        yield 'async bucket'              => [-32350, RpcAsyncRequestException::class];
    }

    #[DataProvider('roundedCodes')]
    public function testUnmappedCodeTakesItsHundred(int $code, string $expected): void
    {
        $exception = AbstractRpcErrorException::fromCode($code, 'rounded');

        $this->assertInstanceOf($expected, $exception);
        $this->assertSame($code, $exception->getCode(), 'сотня обирає клас, код лишається своїм');
        $this->assertSame('rounded', $exception->getMessage());
    }

    /**
     * Два випадки, де округлення навмисно мовчить.
     *
     * Нижче -32700 специфікація лишає простір «for future use» — там немає ні
     * наших родин, ні чужих значень. А сотня без класу (-32200, -32100) — це
     * не підказка, а здогад.
     */
    public static function unroundableCodes(): iterable
    {
        yield 'reserved floor'         => [-32768];
        yield 'below lowest defined'   => [-32750];
        yield 'hundred has no class'   => [-32250];
        yield 'just above server band' => [-32100];
    }

    #[DataProvider('unroundableCodes')]
    public function testCodeWithoutFamilyStaysInternal(int $code): void
    {
        $exception = AbstractRpcErrorException::fromCode($code, 'unmapped');

        $this->assertInstanceOf(RpcInternalException::class, $exception);
        $this->assertSame($code, $exception->getCode(), 'код має лишитись тим самим');
    }

    /** Другий елемент — код, який реально опиниться у винятку. */
    public static function applicationCodes(): iterable
    {
        yield 'below reserved floor' => [-32769, -32769];
        yield 'far below'            => [-40000, -40000];
        yield 'above reserved range' => [-31999, -31999];
        yield 'http-like code'       => [500, 500];
        yield 'service code'         => [4010, 4010];
    }

    /**
     * Код поза межами протоколу належить застосунку — і має доїхати незмінним.
     *
     * Раніше такий код відхилявся, а до повідомлення приліплювалось
     * `EMNF (…)`: помилка застосунку приходила до клієнта спотвореною і з
     * чужим класом.
     */
    #[DataProvider('applicationCodes')]
    public function testCodeOutsideProtocolRangeBecomesApplicationError(int $code, int $expectedCode): void
    {
        $exception = AbstractRpcErrorException::fromCode($code, 'rule broken');

        $this->assertInstanceOf(CustomApplicationException::class, $exception);
        $this->assertSame($expectedCode, $exception->getCode());
        $this->assertSame('rule broken', $exception->getMessage(), 'повідомлення застосунку не переписується');
    }

    /**
     * Діапазон, який специфікація лишає реалізації сервера: -32000…-32099.
     *
     * Верхня межа саме -32000, а не -32001: у специфікації рядок «-32000 to
     * -32099 Server error» включає обидва кінці.
     */
    public static function customServerCodes(): iterable
    {
        yield 'top'    => [-32000];
        yield 'second' => [-32001];
        yield 'middle' => [-32050];
        yield 'bottom' => [-32099];
    }

    #[DataProvider('customServerCodes')]
    public function testCustomServerRangeHasItsOwnClass(int $code): void
    {
        $exception = AbstractRpcErrorException::fromCode($code, 'transport hiccup');

        $this->assertInstanceOf(RpcCustomServerException::class, $exception);
        $this->assertSame($code, $exception->getCode(), 'округлення до сотні не має ковтати цей діапазон');
    }

    /**
     * Інваріант на весь простір кодів: fromCode() завжди повертає виняток і
     * ніколи не губить код. Третього не дано — саме це й робить її придатною
     * для відновлення помилки на іншому кінці дроту.
     */
    public function testEveryCodeYieldsAnExceptionKeepingItsCode(): void
    {
        foreach ([-40000, -32769, -32768, -32700, -32603, -32404, -32250, -32099, -32001, -32000, -31999, 500, 4010] as $code) {
            $exception = AbstractRpcErrorException::fromCode($code, 'msg');

            $this->assertInstanceOf(AbstractRpcErrorException::class, $exception);
            $this->assertSame($code, $exception->getCode(), 'код має пережити відновлення');
        }
    }

    public function testFromCodeIsLateStaticBoundToTheCalledClass(): void
    {
        $this->assertInstanceOf(
            RpcMethodNotFoundExceptionRpc::class,
            RpcBadParamException::fromCode(-32601, 'via subclass')
        );
    }

    // --------------------------------------------------------------- fromArray

    public function testFromArrayUsesCodeAndMessage(): void
    {
        $exception = AbstractRpcErrorException::fromArray(['code' => -32601, 'message' => 'nope']);

        $this->assertInstanceOf(RpcMethodNotFoundExceptionRpc::class, $exception);
        $this->assertSame(-32601, $exception->getCode());
        $this->assertSame('nope', $exception->getMessage());
    }

    public function testFromArrayIgnoresExtraKeys(): void
    {
        $exception = AbstractRpcErrorException::fromArray([
            'code' => -32602,
            'message' => 'bad param',
            'data' => ['whatever' => true],
            'id' => 7,
        ]);

        $this->assertInstanceOf(RpcBadParamException::class, $exception);
        $this->assertSame('bad param', $exception->getMessage());
    }

    /** Масив без коду — це «щось пішло не так у нас»: внутрішня помилка. */
    public function testFromArrayWithMissingCodeBecomesInternalError(): void
    {
        $exception = AbstractRpcErrorException::fromArray(['message' => 'only a message']);

        $this->assertInstanceOf(RpcInternalException::class, $exception);
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame('only a message', $exception->getMessage());
    }

    public function testFromArrayWithMissingMessageUsesClassDefault(): void
    {
        $exception = AbstractRpcErrorException::fromArray(['code' => -32404]);

        $this->assertInstanceOf(RpcDataNotFoundException::class, $exception);
        $this->assertSame('Api method returned error "Data not found"', $exception->getMessage());
    }

    public function testFromEmptyArrayBecomesInternalErrorWithClassMessage(): void
    {
        $exception = AbstractRpcErrorException::fromArray([]);

        $this->assertInstanceOf(RpcInternalException::class, $exception);
        $this->assertSame(-32603, $exception->getCode());
    }

    // ---------------------------------------------------------------- fromJson

    public function testFromJsonMatchesFromArray(): void
    {
        $exception = AbstractRpcErrorException::fromJson('{"code":-32602,"message":"bad param"}');

        $this->assertInstanceOf(RpcBadParamException::class, $exception);
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame('bad param', $exception->getMessage());
    }

    public function testFromJsonOfEmptyObjectBecomesInternalError(): void
    {
        $exception = AbstractRpcErrorException::fromJson('{}');

        $this->assertInstanceOf(RpcInternalException::class, $exception);
        $this->assertSame(-32603, $exception->getCode());
    }

    /** Payload, що не розбирається в масив: і поламаний JSON, і валідний скаляр. */
    public static function notAnArrayPayloads(): iterable
    {
        yield 'malformed'    => ['not a json'];
        yield 'empty string' => [''];
        yield 'truncated'    => ['{"code":-32601'];
        yield 'string'       => ['"just a string"'];
        yield 'number'       => ['5'];
        yield 'float'        => ['1.5'];
        yield 'bool'         => ['true'];
        yield 'null'         => ['null'];
    }

    /**
     * Раніше такий payload валив fromJson() з TypeError з надрах fromArray().
     */
    #[DataProvider('notAnArrayPayloads')]
    public function testFromJsonWithoutUsableArrayBecomesParseError(string $payload): void
    {
        $exception = AbstractRpcErrorException::fromJson($payload);

        $this->assertInstanceOf(RpcJsonParseException::class, $exception);
        $this->assertSame(-32700, $exception->getCode());
        $this->assertSame('Invalid JSON was received by the server.', $exception->getMessage());
    }

    /** Масиви йдуть старим шляхом — через fromArray(). */
    public function testFromJsonKeepsArrayPayloadsOnTheFromArrayPath(): void
    {
        $this->assertInstanceOf(RpcInternalException::class, AbstractRpcErrorException::fromJson('[]'));
        $this->assertSame(-32603, AbstractRpcErrorException::fromJson('[]')->getCode());
        $this->assertInstanceOf(RpcInternalException::class, AbstractRpcErrorException::fromJson('[1,2,3]'));
    }

    // ----------------------------------------------------------- fromThrowable

    public function testFromThrowableKeepsPreviousByDefault(): void
    {
        $origin = new \RuntimeException('origin', -32601);

        $exception = AbstractRpcErrorException::fromThrowable($origin);

        $this->assertInstanceOf(RpcMethodNotFoundExceptionRpc::class, $exception);
        $this->assertSame(-32601, $exception->getCode());
        $this->assertSame('origin', $exception->getMessage());
        $this->assertSame($origin, $exception->getPrevious());
    }

    public function testFromThrowableCanDropPrevious(): void
    {
        $exception = AbstractRpcErrorException::fromThrowable(new \RuntimeException('x', -32601), false);

        $this->assertNull($exception->getPrevious());
    }

    /**
     * Звичайний PHP-виняток коду не ставить — у нього нуль.
     *
     * Нуль означає «коду немає», а не «код застосунку»: за специфікацією
     * непередбачена помилка сервера — це Internal error. Інакше кожен чужий
     * виняток видавав би себе за прикладну помилку.
     */
    public function testThrowableWithoutCodeBecomesInternalError(): void
    {
        $exception = AbstractRpcErrorException::fromThrowable(new \LogicException('plain', 0));

        $this->assertInstanceOf(RpcInternalException::class, $exception);
        $this->assertSame(AbstractRpcErrorException::DEFAULT_CODE, $exception->getCode());
        $this->assertSame('plain', $exception->getMessage(), 'повідомлення чужого винятку зберігається');
    }

    /** Те саме правило для прямого виклику. */
    public function testZeroCodeIsTreatedAsNoCode(): void
    {
        $exception = AbstractRpcErrorException::fromCode(0, 'no code given');

        $this->assertInstanceOf(RpcInternalException::class, $exception);
        $this->assertSame(-32603, $exception->getCode());
    }

    /**
     * \Exception::__construct() присвоює $code лише коли він не нульовий —
     * саме на цьому й тримається оголошений `protected $code` нащадка.
     */
    public function testZeroCodeLeavesDeclaredClassCodeIntact(): void
    {
        $this->assertSame(-32404, (new RpcDataNotFoundException('m', 0))->getCode());
        $this->assertSame(-32700, (new RpcJsonParseException('m', 0))->getCode());
        // А ось через fromThrowable() нуль означає «коду немає» — і стає -32603.
        $this->assertSame(-32603, AbstractRpcErrorException::fromThrowable(new \LogicException('m', 0))->getCode());
    }

    /**
     * fromThrowable() — це той самий fromCode(), тільки з чужого винятку: ті
     * самі три правила й той самий код на виході.
     */
    public function testFromThrowableFollowsTheSameRulesAsFromCode(): void
    {
        $reserved = AbstractRpcErrorException::fromThrowable(new \RuntimeException('near logic', -32499));
        $this->assertInstanceOf(RpcLogicException::class, $reserved, 'та сама сотня, що й у fromCode()');
        $this->assertSame(-32499, $reserved->getCode());

        $outOfRange = AbstractRpcErrorException::fromThrowable(new \RuntimeException('http', 500));
        $this->assertInstanceOf(CustomApplicationException::class, $outOfRange);
        $this->assertSame(500, $outOfRange->getCode());
    }

    public function testFromThrowableAcceptsRpcExceptionAndPreservesItsType(): void
    {
        $origin = new RpcBadParamException('param missing', -32602);

        $exception = AbstractRpcErrorException::fromThrowable($origin);

        $this->assertInstanceOf(RpcBadParamException::class, $exception);
        $this->assertNotSame($origin, $exception, 'Повертається новий інстанс, а не той самий обʼєкт');
        $this->assertSame($origin, $exception->getPrevious());
    }

    // ----------------------------------------------------------- constructor

    public function testDefaultCodeIsInternalError(): void
    {
        $this->assertSame(-32603, (new RpcInternalException())->getCode());
        $this->assertSame(-32603, AbstractRpcErrorException::DEFAULT_CODE);
    }

    public function testExplicitMessageAndCodeWin(): void
    {
        $previous = new \RuntimeException('root');
        $exception = new RpcInternalException('custom', -32500, $previous);

        $this->assertSame('custom', $exception->getMessage());
        $this->assertSame(-32500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testEmptyMessageFallsBackToClassDefaultMessage(): void
    {
        $this->assertSame('Internal JSON-RPC error', (new RpcInternalException(''))->getMessage());
    }

    /**
     * Конструктор перевіряє повідомлення через !empty(), тому "0" вважається
     * порожнім і підміняється дефолтним текстом класу. Фіксуємо як є.
     */
    public function testZeroStringMessageIsTreatedAsEmpty(): void
    {
        $this->assertSame('Internal JSON-RPC error', (new RpcInternalException('0'))->getMessage());
    }

    /**
     * Кинутий клас сам називає свій код — і саме цей код бачить клієнт.
     *
     * Тримається через нуль у дефолті конструктора: PHP ігнорує `$code = 0` і
     * лишає значення, оголошене класом. Варто комусь повернути туди
     * `self::DEFAULT_CODE` — і кожен виняток знову стане -32603, а цей тест
     * саме про це й падатиме.
     */
    #[DataProvider('mappedCodes')]
    public function testDirectInstantiationKeepsDeclaredCode(int $declaredCode, string $class): void
    {
        $this->assertSame(
            $declaredCode,
            (new $class())->getCode(),
            'Оголошений $code має пережити конструктор'
        );
        $this->assertSame(
            $declaredCode,
            (new \ReflectionClass($class))->getDefaultProperties()['code'],
            'І збігатися з ключем ERROR_MAPPING'
        );
    }

    /** Переданий явно код важить більше за оголошений — інакше не було б як. */
    public function testExplicitCodeWins(): void
    {
        $this->assertSame(4010, (new RpcDataNotFoundException('rule broken', 4010))->getCode());
    }

    /** Нуль означає «не називали» — лишається код класу, а не нуль. */
    public function testZeroCodeMeansDeclaredOne(): void
    {
        $this->assertSame(-32404, (new RpcDataNotFoundException('m', 0))->getCode());
    }

    // ------------------------------------------------------------ extra data

    public function testFromThrowableCarriesExtraData(): void
    {
        $origin = (new RpcBadParamException('param missing'))->pushToExtraData(['field' => 'email']);

        $this->assertSame(['field' => 'email'], AbstractRpcErrorException::fromThrowable($origin)->getExtraData());
    }

    public function testFromThrowableCarriesExtraDataEvenWithoutPrevious(): void
    {
        $origin = (new RpcBadParamException('param missing'))->pushToExtraData(['field' => 'email']);

        $rebuilt = AbstractRpcErrorException::fromThrowable($origin, false);

        $this->assertSame(['field' => 'email'], $rebuilt->getExtraData());
        $this->assertNull($rebuilt->getPrevious());
    }

    /** У чужого винятку торби немає, і вигадувати її нізвідки. */
    public function testFromThrowableOfForeignThrowableLeavesTheBagEmpty(): void
    {
        $this->assertSame([], AbstractRpcErrorException::fromThrowable(new \RuntimeException('boom', 500))->getExtraData());
    }

    public function testFromArrayReadsExtraFromTheTopLevel(): void
    {
        $exception = AbstractRpcErrorException::fromArray([
            'code' => -32602,
            'message' => 'param missing',
            'extra' => ['field' => 'email'],
        ]);

        $this->assertSame(['field' => 'email'], $exception->getExtraData());
    }

    /** Форма, яку будує сервер: дамп трансформера лежить у data. */
    public function testFromArrayReadsExtraFromTheWireShape(): void
    {
        $exception = AbstractRpcErrorException::fromArray([
            'code' => -32602,
            'message' => 'param missing',
            'data' => [
                'exception' => RpcBadParamException::class,
                'extra' => ['field' => 'email'],
            ],
        ]);

        $this->assertSame(['field' => 'email'], $exception->getExtraData());
    }

    public function testFromArrayWithoutExtraLeavesTheBagEmpty(): void
    {
        $this->assertSame([], AbstractRpcErrorException::fromArray(['code' => -32602])->getExtraData());
        $this->assertSame([], AbstractRpcErrorException::fromArray(['code' => -32602, 'data' => 'plain string'])->getExtraData());
    }

    public function testFromArrayIgnoresExtraThatIsNotAnArray(): void
    {
        $exception = AbstractRpcErrorException::fromArray(['code' => -32602, 'extra' => 'nope']);

        $this->assertSame([], $exception->getExtraData());
    }

    /** Дані з дрота проходять той самий фільтр, що й свої. */
    public function testIncomingExtraIsFiltered(): void
    {
        $exception = AbstractRpcErrorException::fromArray([
            'code' => -32602,
            'extra' => ['ok' => 1, 'bad' => new \stdClass()],
        ]);

        $this->assertSame(['ok' => 1], $exception->getExtraData());
    }

    public function testFromJsonRestoresExtra(): void
    {
        $exception = AbstractRpcErrorException::fromJson('{"code":-32602,"message":"m","data":{"extra":{"field":"email"}}}');

        $this->assertInstanceOf(RpcBadParamException::class, $exception);
        $this->assertSame(['field' => 'email'], $exception->getExtraData());
    }

    /** Те, заради чого торба існує: дані доїжджають до клієнта й піднімаються назад у виняток. */
    public function testExtraDataSurvivesTheWholeRoundTrip(): void
    {
        $origin = (new RpcBadParamException('param missing'))->pushToExtraData(['field' => 'email', 'attempt' => 3]);

        $onTheWire = json_encode([
            'code' => $origin->getCode(),
            'message' => $origin->getMessage(),
            'data' => (new ExceptionToArrayTransformer($origin, 'prod'))->infoByEnvironment(),
        ]);

        $restored = AbstractRpcErrorException::fromJson($onTheWire);

        $this->assertInstanceOf(RpcBadParamException::class, $restored);
        $this->assertSame(-32602, $restored->getCode());
        $this->assertSame(['field' => 'email', 'attempt' => 3], $restored->getExtraData());
    }

    // -------------------------------------------------------------- mapping

    /**
     * Мапа і класи — два джерела однієї правди, тож вони мусять збігатися.
     *
     * Розійтися вони можуть тихо: досить додати клас і забути рядок у мапі (або
     * навпаки), і код помилки почне залежати від того, яким шляхом виняток
     * потрапив до клієнта — кинутий напряму чи відновлений із коду.
     */
    public function testMappingAgreesWithDeclaredCodes(): void
    {
        foreach (AbstractRpcErrorException::getMapping() as $code => $class) {
            $this->assertSame(
                $code,
                (new \ReflectionClass($class))->getDefaultProperties()['code'] ?? null,
                sprintf('%s у мапі стоїть під %d, а оголошує інший код', $class, $code),
            );
            $this->assertSame($code, (new $class())->getCode(), sprintf('%s має віддавати %d', $class, $code));
        }
    }

    /**
     * Зворотний бік тієї ж перевірки: клас із власним кодом має бути в мапі.
     *
     * Два винятки навмисні. `WrongWayException` — внутрішній сигнал потоку
     * керування, він назовні не їде; `ConstraintsImposedException` уточнює
     * -32602 і ділить код із `RpcBadParamException`, а ключ у мапі один.
     */
    public function testEveryExceptionWithOwnCodeIsMapped(): void
    {
        $mapped = array_flip(AbstractRpcErrorException::getMapping());
        $allowedOutside = [
            WrongWayException::class,
            ConstraintsImposedException::class,
            // Ці двоє представляють діапазони, а не окремі коди: у мапі їм
            // немає що займати, їх обирають межі в fromCode().
            RpcCustomServerException::class,
            CustomApplicationException::class,
        ];

        foreach (glob(__DIR__.'/../../src/*.php') as $file) {
            $class = 'Ufo\\RpcError\\'.basename($file, '.php');

            if (!class_exists($class) || !is_subclass_of($class, AbstractRpcErrorException::class)) {
                continue;
            }
            if (\in_array($class, $allowedOutside, true)) {
                continue;
            }

            $this->assertArrayHasKey($class, $mapped, sprintf('%s оголошує код, але в мапі його немає', $class));
        }
    }

    public function testGetRpcErrorsListIsAliasOfGetMapping(): void
    {
        $this->assertSame(AbstractRpcErrorException::getMapping(), AbstractRpcErrorException::getRpcErrorsList());
    }

    /** Межі — дослівно зі специфікації JSON-RPC 2.0, і це не місце для творчості. */
    public function testReservedRangeMatchesSpecification(): void
    {
        $this->assertSame(-32768, AbstractRpcErrorException::MIN_ERROR_CODE);
        $this->assertSame(-32000, AbstractRpcErrorException::MAX_ERROR_CODE);
    }

    public function testMappingCodesLieInsideReservedRange(): void
    {
        foreach (array_keys(AbstractRpcErrorException::getMapping()) as $code) {
            $this->assertGreaterThanOrEqual(AbstractRpcErrorException::MIN_ERROR_CODE, $code);
            $this->assertLessThanOrEqual(AbstractRpcErrorException::MAX_ERROR_CODE, $code);
        }
    }

    public function testRangeConstants(): void
    {
        $this->assertSame(-32768, AbstractRpcErrorException::MIN_ERROR_CODE);
        $this->assertSame(-32000, AbstractRpcErrorException::MAX_ERROR_CODE);
        $this->assertSame(100, AbstractRpcErrorException::ROUNDING_BASE);
        $this->assertSame(-32700, AbstractRpcErrorException::ROUNDING_MIN_CODE);
    }
}
