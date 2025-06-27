<?php

namespace Essentio\Core\Extra;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use Exception;

/**
 * @api
 */
final class Cast
{
    /**
     * Cast input to bool or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function bool(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?bool {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $bool = filter_var($input, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($bool === null) {
                throw new Exception($message);
            }

            return $bool;
        };
    }

    /**
     * Cast input to DateTimeImmutable or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function date(string $message = ""): Closure
    {
        return function (string $input) use ($message): DateTimeImmutable|null {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            try {
                return new DateTimeImmutable($input);
            } catch (Exception) {
                throw new Exception($message);
            }
        };
    }

    /**
     * Cast input to enum value or throw.
     *
     * @param class-string<BackedEnum> $enumClass
     * @param string $message
     * @return Closure
     */
    public static function enum(string $enumClass, string $message = ""): Closure
    {
        if (!enum_exists($enumClass)) {
            throw new Exception("Invalid enum class: $enumClass");
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new Exception("Enum must be a backed enum");
        }

        return function (string $input) use ($enumClass, $message): ?BackedEnum {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $enum = $enumClass::tryFrom($input);

            if ($enum === null) {
                throw new Exception($message);
            }

            return $enum;
        };
    }

    /**
     * Cast input to float or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function float(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?float {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);
            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($floatVal === false) {
                throw new Exception($message);
            }

            return $floatVal;
        };
    }

    /**
     * Cast input to int or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function int(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?int {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);
            $intVal = filter_var($value, FILTER_VALIDATE_INT);

            if ($intVal === false) {
                throw new Exception($message);
            }

            return $intVal;
        };
    }

    /**
     * Cast input to int or float or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function number(string $message = ""): Closure
    {
        return function (string $input) use ($message): int|float|null {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($intVal = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
                return $intVal;
            }

            if (($floatVal = filter_var($value, FILTER_VALIDATE_FLOAT)) !== false) {
                return $floatVal;
            }

            throw new Exception($message);
        };
    }

    /**
     * Return string input, optionally trimmed.
     *
     * @param bool $trim
     * @return Closure
     */
    public static function string(bool $trim = false): Closure
    {
        return function (string $input) use ($trim): string {
            if ($trim) {
                return trim($input);
            }

            return $input;
        };
    }

    /**
     * Return null if input is empty.
     *
     * @param string $input
     * @return mixed
     */
    protected static function nullOnEmpty(string $input): mixed
    {
        if (trim($input) === "") {
            return null;
        }

        return $input;
    }

    /**
     * Extract number from input string.
     *
     * @param string $input
     * @param string $message
     * @return string
     */
    protected static function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);

        if (empty($matches[0])) {
            throw new Exception($message);
        }

        return $matches[0][0];
    }
}
