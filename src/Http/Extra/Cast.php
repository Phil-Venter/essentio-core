<?php

declare(strict_types=1);

namespace Essentio\Http\Extra;

use Essentio\Http\ValidationException;

use Closure;
use DateTimeImmutable;
use Throwable;
use BackedEnum;

use function enum_exists;
use function filter_var;
use function is_subclass_of;
use function preg_match_all;
use function trim;

/**
 * @api
 */
class Cast
{
    /**
     * Cast input to bool or throw.
     */
    public static function bool(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?bool {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            if (($bool = filter_var($input, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)) === null) {
                throw new ValidationException($message);
            }

            return $bool;
        };
    }

    /**
     * Cast input to DateTimeImmutable or throw.
     */
    public static function date(string $message = ""): Closure
    {
        return function (string $input) use ($message): DateTimeImmutable|null {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            try {
                return new DateTimeImmutable($input);
            } catch (Throwable $throwable) {
                throw new ValidationException($message, previous: $throwable);
            }
        };
    }

    /**
     * Cast input to enum value or throw.
     *
     * @param class-string<BackedEnum> $enumClass
     */
    public static function enum(string $enumClass, string $message = ""): Closure
    {
        if (!enum_exists($enumClass)) {
            throw new ValidationException("Invalid enum class: " . $enumClass);
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new ValidationException("Enum must be a backed enum");
        }

        return function (string $input) use ($enumClass, $message): ?BackedEnum {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            try {
                return $enumClass::from($input);
            } catch (Throwable $throwable) {
                throw new ValidationException($message, previous: $throwable);
            }
        };
    }

    /**
     * Cast input to float or throw.
     */
    public static function float(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?float {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($floatVal = filter_var($value, FILTER_VALIDATE_FLOAT)) === false) {
                throw new ValidationException($message);
            }

            return $floatVal;
        };
    }

    /**
     * Cast input to int or throw.
     */
    public static function int(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?int {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($intVal = filter_var($value, FILTER_VALIDATE_INT)) === false) {
                throw new ValidationException($message);
            }

            return $intVal;
        };
    }

    /**
     * Cast input to int or float or throw.
     */
    public static function number(string $message = ""): Closure
    {
        return function (string $input) use ($message): int|float|null {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($intVal = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
                return $intVal;
            }

            if (($floatVal = filter_var($value, FILTER_VALIDATE_FLOAT)) !== false) {
                return $floatVal;
            }

            throw new ValidationException($message);
        };
    }

    /**
     * Return string input, optionally trimmed.
     */
    public static function string(bool $trim = false): Closure
    {
        return function (?string $input) use ($trim): ?string {
            if ($input === null) {
                return null;
            }

            return $trim ? trim($input) : $input;
        };
    }

    /**
     * Return null if input is empty.
     */
    protected static function nullOnEmpty(string $input): mixed
    {
        return trim($input) === "" ? null : $input;
    }

    /**
     * Extract number from input string.
     */
    protected static function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);
        return empty($matches[0]) ? throw new ValidationException($message) : $matches[0][0];
    }
}
