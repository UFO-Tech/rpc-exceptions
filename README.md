# ufo-tech/rpc-exceptions
### Exception package RPC server error codes

![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) ![Size](https://img.shields.io/github/repo-size/ufo-tech/rpc-exceptions?label=Size%20of%20the%20repository) ![package_version](https://img.shields.io/github/v/tag/ufo-tech/rpc-exceptions?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) ![fork](https://img.shields.io/github/forks/ufo-tech/rpc-exceptions?color=green&logo=github&style=flat)
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/rpc-exceptions/php?logo=PHP&logoColor=white)
## Problem
When a rpc call encounters an error, the Response Object MUST contain the error member with a value that is a Object with the following members:

## Installation

Requires PHP 8.4 or newer.

```console
composer require ufo-tech/rpc-exceptions
```

## Error codes

| Code | Exception | Meaning |
| --- | --- | --- |
| `-32700` | `RpcJsonParseException` | Invalid JSON |
| `-32600` | `RpcBadRequestException` | Invalid request |
| `-32601` | `RpcMethodNotFoundExceptionRpc` | Unknown method |
| `-32602` | `RpcBadParamException` | Invalid parameters |
| `-32603` | `RpcInternalException` | Internal error |
| `-32500` | `RpcRuntimeException` | Runtime error |
| `-32400` | `RpcLogicException` | Logic error |
| `-32401` | `RpcTokenNotSentException` | Missing token |
| `-32403` | `RpcInvalidTokenException` | Invalid token |
| `-32404` | `RpcDataNotFoundException` | Data not found |
| `-32300` | `RpcAsyncRequestException` | Invalid async request |
| `-32301` | `RpcInvalidBatchRequestExceptions` | Invalid batch |
| `-32099` to `-32000` | `RpcCustomServerException` | Server error |
| Outside `-32768` to `-32000` | `CustomApplicationException` | Application error |

For other reserved codes, the nearest hundred usually determines the class: `-32450` resolves to `RpcLogicException`. Codes in `-32299` to `-32100` and `-32768` to `-32701` resolve to `RpcInternalException`. The original code is preserved; `0` uses the default (`-32603` on the base class). Use codes outside the reserved range for application errors.

The standard JSON-RPC codes are `-32700`, `-32600` to `-32603`, and `-32099` to `-32000`. Other reserved codes above are defined by this package.

## Create an exception

```php
use Ufo\RpcError\AbstractRpcErrorException;

$error = AbstractRpcErrorException::fromCode(-32700);
$error = AbstractRpcErrorException::fromArray(['code' => -32600, 'message' => 'Invalid request']);
$error = AbstractRpcErrorException::fromJson('{"code":-32500,"message":"Upstream failed"}');
$error = AbstractRpcErrorException::fromThrowable(new \RuntimeException('Failed', 500));
```

`fromCode()` selects a class by code. `fromArray()` and `fromJson()` read an error response; invalid JSON yields `RpcJsonParseException`. `fromThrowable()` creates a new exception and keeps the original as `previous` by default. It needs an integer code; non-numeric codes such as SQLSTATE must be mapped first. Numeric strings are converted to integers.

Call `AbstractRpcErrorException::getMapping()` (or `getRpcErrorsList()`) for the exact mapping.

## Application codes

```php
use Ufo\RpcError\CustomApplicationException;

throw new CustomApplicationException('Limit reached', 4010);
```

To resolve your own code to a specific class, extend the mapping and call `fromCode()` on your subclass:

```php
use Ufo\RpcError\AbstractRpcErrorException;
use Ufo\RpcError\IProcedureExceptionInterface;

class UnsupportedProviderException extends AbstractRpcErrorException implements IProcedureExceptionInterface
{
    protected $code = -31050;
    protected $message = 'Unsupported provider';
}

class MyRpcError extends AbstractRpcErrorException
{
    const array ERROR_MAPPING = [
        -31050 => UnsupportedProviderException::class,
    ] + parent::ERROR_MAPPING;
}

MyRpcError::fromCode(-31050); // UnsupportedProviderException
```

Mapped classes must extend `AbstractRpcErrorException` and accept `(string $message, int $code, ?Throwable $previous)` in their constructor. Entries on the left of `+` take precedence.

## Catch by category

| Interface | Error type |
| --- | --- |
| `IUserInputExceptionInterface` | Invalid input, method, parameters, async request or batch |
| `ISecurityExceptionInterface` | Authentication or authorization failure |
| `IServerExceptionInterface` | RPC or server failure |
| `IProcedureExceptionInterface` | Application-defined procedure error |

The category follows the resolved class. `RpcLogicException` and `RpcRuntimeException` are server errors.

## Extra data

Package exceptions carry an optional data bag:

```php
throw (new RpcBadParamException('Invalid input'))
    ->pushToExtraData(['field' => 'email', 'attempt' => 3]);
```

`changeExtraData()` replaces the bag; `pushToExtraData()` merges into it. Both return the exception. Only scalar values, `null`, and nested arrays are kept. Pass acyclic arrays: circular references can exhaust memory.

`fromThrowable()` copies the bag. `fromArray()` and `fromJson()` restore it from `extra` or `data.extra`:

```php
$restored = AbstractRpcErrorException::fromJson($errorObjectFromTheWire);
$restored->getExtraData(); // ['field' => 'email', 'attempt' => 3]
```

## Error responses

`ExceptionToArrayTransformer` formats a throwable for an RPC response. Only `dev` and `test` include the full dump; other environments include the class alone. The `extra` key appears only when the bag is not empty.

```php
use Ufo\RpcError\ExceptionToArrayTransformer;

$data = (new ExceptionToArrayTransformer($error, 'prod'))->infoByEnvironment();
```

`getFullInfo()` and `getShortInfo()` select a format directly. Nested `previous` exceptions still use `infoByEnvironment()`. The reported code is preserved when convertible to a nonzero integer; otherwise it falls back to `-32603`.

For constraint errors, `ConstraintsImposedException` extends `-32602` and takes the constraint list as its second argument:

```php
throw new ConstraintsImposedException('Invalid payload', ['email' => 'NotBlank']);
```

