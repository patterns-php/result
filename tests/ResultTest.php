<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Patterns\Result;
use Patterns\ResultError;

final class ResultTest extends TestCase
{
    public function testSuccessState(): void
    {
        $result = Result::success(['id' => 1]);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame(['id' => 1], $result->value());
        $this->assertNull($result->error());
        $this->assertNull($result->errorMessage());
        $this->assertNull($result->errorCause());
    }

    public function testFailureState(): void
    {
        $cause = new RuntimeException('underlying');
        $result = Result::fail('boom', $cause, 'ctx-1', 'ctx-2');

        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('boom', $result->errorMessage());
        $this->assertSame($cause, $result->errorCause());
        $this->assertInstanceOf(ResultError::class, $result->error());
        $this->assertSame(['ctx-1', 'ctx-2'], $result->error()?->data);
    }

    public function testValueThrowsOnFailure(): void
    {
        $this->expectException(LogicException::class);
        Result::fail('nope')->value();
    }

    public function testOnSuccessAndOnFailure(): void
    {
        $seen = [];

        Result::success(5)
            ->onSuccess(function (int $value) use (&$seen): void {
                $seen[] = "ok:{$value}";
            })
            ->onFailure(function (string $message) use (&$seen): void {
                $seen[] = "fail:{$message}";
            });

        Result::fail('bad')
            ->onSuccess(function () use (&$seen): void {
                $seen[] = 'should-not-run';
            })
            ->onFailure(function (string $message, $cause, $data) use (&$seen): void {
                $seen[] = "fail:{$message}";
            });

        $this->assertSame(['ok:5', 'fail:bad'], $seen);
    }

    public function testMap(): void
    {
        $this->assertSame(10, Result::success(5)->map(static fn (int $v): int => $v * 2)->value());

        $mapped = Result::fail('boom')->map(static fn (int $v): int => $v * 2);
        $this->assertTrue($mapped->isFailure());
        $this->assertSame('boom', $mapped->errorMessage());
    }

    public function testMapPreservesErrorDetails(): void
    {
        $cause = new RuntimeException('root');
        $mapped = Result::fail('boom', $cause, 'ctx')
            ->map(static fn ($v) => $v);

        $this->assertSame('boom', $mapped->errorMessage());
        $this->assertSame($cause, $mapped->errorCause());
        $this->assertSame(['ctx'], $mapped->error()?->data);
    }

    public function testFlatMap(): void
    {
        $chained = Result::success(3)->flatMap(static fn (int $v): Result => Result::success($v + 1));
        $this->assertSame(4, $chained->value());

        $failed = Result::success(3)->flatMap(static fn (): Result => Result::fail('inner'));
        $this->assertTrue($failed->isFailure());
        $this->assertSame('inner', $failed->errorMessage());
    }

    public function testRecover(): void
    {
        $this->assertSame(5, Result::success(5)->recover(0)->value());
        $this->assertSame(0, Result::fail('x')->recover(0)->value());
        $this->assertTrue(Result::fail('x')->recover(0)->isSuccess());
    }

    public function testRecoverWith(): void
    {
        $recovered = Result::fail('boom', null, 'ctx')->recoverWith(
            static fn (?ResultError $error): string => 'fallback:' . $error?->message
        );

        $this->assertTrue($recovered->isSuccess());
        $this->assertSame('fallback:boom', $recovered->value());
    }

    public function testEnsure(): void
    {
        $passed = Result::success(10)->ensure(static fn (int $v): bool => $v > 5, 'too small');
        $this->assertTrue($passed->isSuccess());

        $failed = Result::success(1)->ensure(static fn (int $v): bool => $v > 5, 'too small');
        $this->assertTrue($failed->isFailure());
        $this->assertSame('too small', $failed->errorMessage());

        // ensure on a failure passes the failure through untouched
        $alreadyFailed = Result::fail('original')->ensure(static fn (): bool => true, 'ignored');
        $this->assertSame('original', $alreadyFailed->errorMessage());
    }

    public function testIsNull(): void
    {
        $this->assertTrue(Result::success(null)->isNull());
        $this->assertFalse(Result::success(0)->isNull());
        $this->assertFalse(Result::fail('x')->isNull());
    }

    public function testCombineAllSuccess(): void
    {
        $combined = Result::combine([
            Result::success('a'),
            Result::success('b'),
            Result::success('c'),
        ]);

        $this->assertTrue($combined->isSuccess());
        $this->assertSame(['a', 'b', 'c'], $combined->value());
    }

    public function testCombineWithFailures(): void
    {
        $combined = Result::combine([
            Result::success('a'),
            Result::fail('first error'),
            Result::fail('second error'),
        ]);

        $this->assertTrue($combined->isFailure());
        $this->assertSame('One or more results failed', $combined->errorMessage());

        $data = $combined->error()?->data ?? [];
        $this->assertCount(2, $data);
        $this->assertInstanceOf(ResultError::class, $data[0]);
        $this->assertSame('first error', $data[0]->message);
        $this->assertSame('second error', $data[1]->message);
    }

    public function testSerialization(): void
    {
        $success = Result::success(42);
        $this->assertSame(['success' => true, 'value' => 42, 'error' => null], $success->toArray());
        $this->assertSame($success->toArray(), $success->jsonSerialize());

        $failure = Result::fail('boom', new RuntimeException('root'));
        $array = $failure->toArray();
        $this->assertFalse($array['success']);
        $this->assertSame('boom', $array['error']['message']);
        $this->assertSame(RuntimeException::class . ': root', $array['error']['cause']);

        // json_encode must not choke on the Throwable
        $this->assertIsString(json_encode($failure, JSON_THROW_ON_ERROR));
    }
}
