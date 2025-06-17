<?php

namespace Essentio\Core\Extra;

use Closure;
use DateTimeInterface;
use Exception;

class Validate
{
    public static function alpha(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match('/^[a-zA-Z]+$/', $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function alphaDash(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match('/^[\w-]+$/', $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function alphaNum(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match('/^[a-zA-Z0-9]+$/', $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function email(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function endsWith(array $suffixes, string $message = ""): Closure
    {
        return function (?string $input) use ($suffixes, $message): ?string {
            if ($input === null) {
                return null;
            }

            foreach ($suffixes as $suffix) {
                if (str_ends_with($input, $suffix)) {
                    return $input;
                }
            }

            throw new Exception($message);
        };
    }

    public static function lowercase(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtolower($input, "UTF-8") !== $input) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function uppercase(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtoupper($input, "UTF-8") !== $input) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function minLength(int $min, string $message = ""): Closure
    {
        return function (?string $input) use ($min, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) < $min) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function maxLength(int $max, string $message = ""): Closure
    {
        return function (?string $input) use ($max, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) > $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function regex(string $pattern, string $message = ""): Closure
    {
        return function (?string $input) use ($pattern, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match($pattern, $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function between(
        DateTimeInterface|float|int $min,
        DateTimeInterface|float|int $max,
        string $message = ""
    ): Closure {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use (
            $min,
            $max,
            $message
        ): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value < $min || $value > $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function gt(DateTimeInterface|float|int $min, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($min, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value <= $min) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function gte(DateTimeInterface|float|int $min, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($min, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value < $min) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function lt(DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value >= $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function lte(DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value > $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function required(string $message = ""): Closure
    {
        return function (mixed $input) use ($message): mixed {
            if (!isset($input) || (is_string($input) && trim($input) === "")) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    public static function inArray(array $allowed, bool $strict = true, string $message = ""): Closure
    {
        return function (mixed $input) use ($allowed, $strict, $message): mixed {
            if ($input === null) {
                return null;
            }

            if (!in_array($input, $allowed, $strict)) {
                throw new Exception($message);
            }

            return $input;
        };
    }
}
