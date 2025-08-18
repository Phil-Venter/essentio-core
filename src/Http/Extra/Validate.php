<?php

declare(strict_types=1);

namespace Essentio\Http\Extra;

use Essentio\Http\ValidationException;

use Closure;
use DateTimeInterface;

use function filter_var;
use function in_array;
use function is_string;
use function mb_strlen;
use function mb_strtolower;
use function mb_strtoupper;
use function preg_match;
use function str_ends_with;
use function trim;

/**
 * @api
 */
class Validate
{
    /**
     * Letters only.
     */
    public static function alpha(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match('/^[a-zA-Z]+$/', $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Letters, numbers, dashes, underscores.
     */
    public static function alphaDash(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match('/^[\w-]+$/', $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Letters and numbers only.
     */
    public static function alphaNum(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match('/^[a-zA-Z0-9]+$/', $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Valid email format.
     */
    public static function email(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must end with one of the given values.
     *
     * @param list<string> $suffixes
     */
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

            throw new ValidationException($message);
        };
    }

    /**
     * Must be lowercase.
     */
    public static function lower(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtolower($input, "UTF-8") !== $input) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be uppercase.
     */
    public static function upper(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtoupper($input, "UTF-8") !== $input) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Minimum string length.
     */
    public static function minLength(int $min, string $message = ""): Closure
    {
        return function (?string $input) use ($min, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) < $min) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Maximum string length.
     */
    public static function maxLength(int $max, string $message = ""): Closure
    {
        return function (?string $input) use ($max, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) > $max) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Matches regex pattern.
     *
     * @param non-empty-string $pattern
     */
    public static function regex(string $pattern, string $message = ""): Closure
    {
        return function (?string $input) use ($pattern, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match($pattern, $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Value must be between min and max.
     */
    public static function between(DateTimeInterface|float|int $min, DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use ($min, $max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value < $min || $value > $max) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be greater than min.
     */
    public static function gt(DateTimeInterface|float|int $min, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (DateTimeInterface|float|int|null $input) use ($min, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value <= $min) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be greater than or equal to min.
     */
    public static function gte(DateTimeInterface|float|int $min, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (DateTimeInterface|float|int|null $input) use ($min, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value < $min) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be less than max.
     */
    public static function lt(DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use ($max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value >= $max) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be less than or equal to max.
     */
    public static function lte(DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use ($max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value > $max) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Input must be present and non-empty.
     */
    public static function required(string $message = ""): Closure
    {
        return function (mixed $input) use ($message): mixed {
            if (!isset($input) || (is_string($input) && trim($input) === "")) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Value must be in allowed set.
     *
     * @param list<mixed> $allowed
     */
    public static function inArray(array $allowed, bool $strict = true, string $message = ""): Closure
    {
        return function (mixed $input) use ($allowed, $strict, $message): mixed {
            if ($input === null) {
                return null;
            }

            if (!in_array($input, $allowed, $strict)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }
}
