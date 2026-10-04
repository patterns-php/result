<?php

declare(strict_types=1);

namespace Patterns;

use JsonSerializable;
use LogicException;
use Throwable;

/**
 * Result pattern - the outcome of an operation that might fail.
 *
 * A Result is either a success (carrying a value) or a failure (carrying an
 * error message, an optional cause, and optional debug data). It makes
 * failure explicit in signatures and enables chaining, instead of relying on
 * exceptions for control flow.
 *
 * Port of @synet/patterns `Result`. PHP idiom note: the TypeScript getters
 * (isSuccess, value, error, errorMessage, errorCause) are methods here, and
 * `combine` is static (it does not use instance state).
 */
final class Result implements JsonSerializable
{
    private function __construct(
        private readonly bool $isSuccess,
        private readonly mixed $value,
        private readonly ?ResultError $error,
    ) {
    }

    // =========================================================================
    // CREATION
    // =========================================================================

    /**
     * A successful result carrying $value.
     */
    public static function success(mixed $value = null): self
    {
        return new self(true, $value, null);
    }

    /**
     * A failed result.
     *
     * @param string             $message Error message describing what went wrong
     * @param Throwable|null     $cause   Optional underlying error
     * @param mixed             ...$data  Optional additional context for debugging
     */
    public static function fail(string $message, ?Throwable $cause = null, mixed ...$data): self
    {
        return new self(false, null, new ResultError($message, $cause, $data));
    }

    // =========================================================================
    // STATE
    // =========================================================================

    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess;
    }

    /**
     * The success value.
     *
     * @throws LogicException when called on a failed result
     */
    public function value(): mixed
    {
        if (!$this->isSuccess) {
            throw new LogicException('Cannot get value from a failed result');
        }

        return $this->value;
    }

    /**
     * The error details if this is a failure, otherwise null.
     */
    public function error(): ?ResultError
    {
        return $this->error;
    }

    public function errorMessage(): ?string
    {
        return $this->error?->message;
    }

    public function errorCause(): ?Throwable
    {
        return $this->error?->cause;
    }

    public function isNull(): bool
    {
        return $this->isSuccess && $this->value === null;
    }

    // =========================================================================
    // CHAINING
    // =========================================================================

    /**
     * Run $fn with the value on success. Returns this result (for chaining).
     */
    public function onSuccess(callable $fn): self
    {
        if ($this->isSuccess) {
            $fn($this->value);
        }

        return $this;
    }

    /**
     * Run $fn with (message, cause, data) on failure. Returns this result.
     */
    public function onFailure(callable $fn): self
    {
        if (!$this->isSuccess && $this->error !== null) {
            $fn($this->error->message, $this->error->cause, $this->error->data);
        }

        return $this;
    }

    /**
     * Transform the success value. Failures pass through unchanged.
     */
    public function map(callable $fn): self
    {
        if ($this->isSuccess) {
            return self::success($fn($this->value));
        }

        return $this->propagateFailure();
    }

    /**
     * Chain to another Result produced from the success value.
     */
    public function flatMap(callable $fn): self
    {
        if ($this->isSuccess) {
            return $fn($this->value);
        }

        return $this->propagateFailure();
    }

    /**
     * Provide a fallback value on failure. Always returns a success.
     */
    public function recover(mixed $defaultValue): self
    {
        return $this->isSuccess ? $this : self::success($defaultValue);
    }

    /**
     * Derive a fallback value from the error on failure. Always returns a success.
     */
    public function recoverWith(callable $fn): self
    {
        return $this->isSuccess ? $this : self::success($fn($this->error));
    }

    /**
     * Enforce a predicate on the success value, otherwise fail with $message.
     */
    public function ensure(callable $condition, string $message): self
    {
        if (!$this->isSuccess) {
            return $this;
        }

        return $condition($this->value) ? $this : self::fail($message);
    }

    // =========================================================================
    // COMBINING
    // =========================================================================

    /**
     * Collect many Results into one.
     *
     * All-success -> success with the list of values.
     * Any failure -> failure carrying the individual errors in `data`.
     *
     * @param array<int, Result> $results
     */
    public static function combine(array $results): self
    {
        $errors = [];
        $values = [];

        foreach ($results as $result) {
            if (!$result instanceof self) {
                throw new LogicException('Result::combine() expects Result instances');
            }

            if ($result->isFailure()) {
                if ($result->error !== null) {
                    $errors[] = $result->error;
                }
            } else {
                $values[] = $result->value;
            }
        }

        if ($errors !== []) {
            return self::fail('One or more results failed', null, ...$errors);
        }

        return self::success($values);
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->isSuccess
            ? ['success' => true, 'value' => $this->value, 'error' => null]
            : ['success' => false, 'value' => null, 'error' => $this->error?->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Rebuild the same failure with identical error details.
     */
    private function propagateFailure(): self
    {
        $error = $this->error;

        return self::fail(
            $error?->message ?? 'Unknown error',
            $error?->cause,
            ...($error?->data ?? [])
        );
    }
}
