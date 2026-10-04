# Patterns — Result

**Represent the outcome of operations that might fail — explicitly, without exceptions.**

Part of the **Patterns** collection: small, rock-solid, dependency-free building
blocks. One pattern, one package.

- Package: `patterns/result`
- Namespace: `Patterns\`
- PHP: `>=8.1` — zero dependencies

---

## Install

```bash
composer require patterns/result
```

## Why

Throwing exceptions for control flow hides failure from signatures and scatters
`try/catch` everywhere. `Result` makes success/failure **explicit**, keeps the
happy path and error handling separate, and lets you chain safely.

```php
use Patterns\Result;

function findUser(int $id): Result
{
    $user = $repository->find($id);

    return $user === null
        ? Result::fail('User not found', null, ['id' => $id])
        : Result::success($user);
}

$result = findUser(42);

if ($result->isSuccess()) {
    render($result->value());
} else {
    error_log($result->errorMessage());
}
```

## API

### Creation

```php
Result::success($value);                          // success
Result::fail($message, $cause?, ...$data);        // failure
```

### State

| Method | Notes |
|---|---|
| `isSuccess(): bool` | |
| `isFailure(): bool` | |
| `value(): mixed` | **throws `LogicException`** if accessed on a failure |
| `error(): ?ResultError` | typed error (`message`, `cause`, `data`) |
| `errorMessage(): ?string` | shorthand for `error()?->message` |
| `errorCause(): ?\Throwable` | shorthand for `error()?->cause` |
| `isNull(): bool` | success **and** value is `null` |

### Chaining

```php
fetchUserData($id)
    ->map(fn ($data) => enrich($data))            // transform the success value
    ->flatMap(fn ($user) => permissions($user))   // chain to another Result
    ->ensure(fn ($p) => $p !== [], 'no permissions')
    ->recover($defaultPermissions)                // fallback value on failure
    ->onSuccess(fn ($p) => render($p))
    ->onFailure(fn ($msg, $cause, $data) => report($msg, $cause, $data));
```

| Method | Behaviour |
|---|---|
| `map(callable)` | transform success value; failures pass through unchanged |
| `flatMap(callable)` | map to another `Result`; failures pass through |
| `recover($default)` | always returns a success (original or default) |
| `recoverWith(callable)` | derive a fallback from the `ResultError` |
| `ensure(callable, string)` | enforce a predicate or fail with the message |
| `onSuccess(callable)` / `onFailure(callable)` | side effects; return `$this` |

### Combining

```php
$all = Result::combine([Result::success(1), Result::success(2)]);  // success([1, 2])
$any = Result::combine([Result::success(1), Result::fail('x')]);   // failure, errors in ->error()->data
```

## PHP notes (vs the TypeScript original)

- TS getters (`isSuccess`, `value`, `error`, …) are **methods** here — idiomatic PHP.
- `combine` is **static** (it never used instance state in the TS).
- `onFailure` unpacks `(message, cause, data)` to match the TS; use `error()` for the struct.
- Added `toArray()` / `JsonSerializable` for serialization.

## Tests

```bash
composer install
composer test
# or
vendor/bin/phpunit
```

## License

MIT.
