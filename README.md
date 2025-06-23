# ufo-tech/rpc-exceptions
### Exception package RPC server error codes

![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185) ![Size](https://img.shields.io/github/repo-size/ufo-tech/rpc-exceptions?label=Size%20of%20the%20repository) ![package_version](https://img.shields.io/github/v/tag/ufo-tech/rpc-exceptions?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185) ![fork](https://img.shields.io/github/forks/ufo-tech/rpc-exceptions?color=green&logo=github&style=flat)
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/rpc-exceptions/php?logo=PHP&logoColor=white)
## Problem
When a rpc call encounters an error, the Response Object MUST contain the error member with a value that is a Object with the following members:

- **code**:
A Number that indicates the error type that occurred.
This MUST be an integer.

- **message**:
A String providing a short description of the error.
The message SHOULD be limited to a concise single sentence.

| Code                  | Type error            | Summary                                            |
|-----------------------|-----------------------|----------------------------------------------------|
| -32700                | Parse error           | Invalid JSON was received by the server            |
| -32600                | Invalid Request       | The JSON sent is not a valid Request object.       |
| -32601                | Method not found      | The method does not exist / is not available.      |
| -32602                | Invalid params        | Invalid method parameter(s).                       |
| -32603                | Internal error        | Internal JSON-RPC error.                           |
| -32500                | Server error          | Runtime error on procedure.                        |
| -32400                | System error          | Logic error on application.                        |
| -32401                | Security error        | Token not found                                    |
| -32403                | Security error        | Invalid token                                      |
| -32404                | Data error            | Requested data not found                           |
| -32300                | Async error           | Error transfer async data                          |
| -32301                | Batch error           | Error batch request                                |
| -32000                | Application error     | Reserved for implementation-defined server-errors. |
| from -32001 to -32099 | Custom user exception | ---                                                |

There are many error codes and identifying the error from the code can be a difficult task.
This library will help you.
You can easily get the exception object from the error code.

| Code                   | Exception class                           |
|------------------------|-------------------------------------------|
| -32700                 |  RpcJsonParseException::class             |
| -32600                 |  RpcBadRequestException::class            |
| -32601                 |  RpcMethodNotFoundExceptionRpc::class     |
| -32602                 |  RpcBadParamException::class              |
| -32603                 |  RpcInternalException::class              |
| -32500                 |  RpcRuntimeException::class               |
| -32400                 |  RpcLogicException::class                 |
| -32401                 |  RpcTokenNotFoundInHeaderException::class |
| -32403                 |  RpcInvalidTokenException::class          |
| -32404                 |  RpcDataNotFoundException::class          |
| -32300                 |  RpcAsyncRequestException::class          |
| -32301                 |  RpcInvalidBatchRequestExceptions::class  |
| -32000                 |  RpcCustomApplicationException::class     |
| from -32001 to -32099" | ---                                       |

## Installation

```console
$ composer require ufo-tech/rpc-exceptions
```

## Get exception object
### From code
```php
use Ufo\RpcError\AbstractRpcErrorException;

$code = -32700;
$message = 'Some custom error message from rpc server'; // optional
$rpcException = AbstractRpcErrorException::fromCode($code);
// return instance of RpcJsonParseException::class
```

### From array
```php
use Ufo\RpcError\AbstractRpcErrorException;

$data = [
    'code' = -32600,
    'message' = 'Some custom error message from rpc server',
];
$rpcException = RpcBadRequestException::fromArray($data);
// return instance of RpcBadRequestException::class
```

### From json
```php
use Ufo\RpcError\AbstractRpcErrorException;

$data = "{\"code\":-32500,\"message\":\"Some custom error message from rpc server\"}";
$rpcException = AbstractRpcErrorException::fromArray($data);
// return instance of RpcRuntimeException::class
```


## Mapping list
```php
use Ufo\RpcError\AbstractRpcErrorException;

$mapping = AbstractRpcErrorException::getRpcErrorsList();
// return array map
[
    -32700 => RpcJsonParseException::class,
    -32600 => RpcBadRequestException::class,
    -32601 => RpcMethodNotFoundExceptionRpc::class,
    -32602 => RpcBadParamException::class,
    -32603 => RpcInternalException::class,
    -32500 => RpcRuntimeException::class,
    -32400 => RpcLogicException::class,
    -32401 => RpcTokenNotFoundInHeaderException::class,
    -32403 => RpcInvalidTokenException::class,
    -32404 => RpcDataNotFoundException::class,
    -32300 => RpcAsyncRequestException::class,
    -32301 => RpcInvalidBatchRequestExceptions::class,
    -32000 => RpcCustomApplicationException::class,
]
```

## Profit