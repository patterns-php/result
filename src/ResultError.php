<?php

declare(strict_types=1);

namespace Patterns;

use JsonSerializable;
use Throwable;

/**
 * Result error details:
 *
 *   { message: string; cause?: Error; data?: unknown[] }
 *
 * Immutable. `data` carries optional debugging context supplied to Result::fail().
 */
final class ResultError implements JsonSerializable
{
    /**
     * @param array<int, mixed> $data
     */
    public function __construct(
        public readonly string $message,
        public readonly ?Throwable $cause = null,
        public readonly array $data = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'cause' => $this->cause === null
                ? null
                : $this->cause::class . ': ' . $this->cause->getMessage(),
            'data' => $this->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->message;
    }
}
