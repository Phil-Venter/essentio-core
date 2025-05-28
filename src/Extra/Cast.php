<?php

namespace Essentio\Core\Extra;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

class Cast
{
    /**
     * Returns a closure that casts input to boolean, or throws an error on failure.
     *
     * @param string $message Error message for invalid boolean.
     * @return Closure(string): ?bool
     */
    public function bool(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?bool {
            $input = $this->nullOnEmpty($input);

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
     * Returns a closure that casts input to DateTimeImmutable, or throws on failure.
     *
     * @param string $message Error message for invalid date.
     * @return Closure(string): ?DateTimeInterface
     */
    public function date(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?DateTimeInterface {
            $input = $this->nullOnEmpty($input);

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
     * Returns a closure that casts input to an enum case using tryFrom().
     *
     * @param class-string<BackedEnum> $enumClass Enum class to resolve.
     * @param string                    $message   Error message if resolution fails.
     * @return Closure(string): ?BackedEnum
     * @throws Exception If class is not a backed enum.
     */
    public function enum(string $enumClass, string $message = ""): Closure
    {
        if (!enum_exists($enumClass)) {
            throw new Exception("Invalid enum class: $enumClass");
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new Exception("Enum must be a backed enum");
        }

        return function (string $input) use ($enumClass, $message): ?BackedEnum {
            $input = $this->nullOnEmpty($input);

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
     * Returns a closure that casts input to float.
     *
     * @param string $message Error message if cast fails.
     * @return Closure(string): ?float
     */
    public function float(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?float {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = $this->normalizeNumber($input, $message);
            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($floatVal === false) {
                throw new Exception($message);
            }

            return $floatVal;
        };
    }

    /**
     * Returns a closure that casts input to int.
     *
     * @param string $message Error message if cast fails.
     * @return Closure(string): ?int
     */
    public function int(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?int {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = $this->normalizeNumber($input, $message);
            $intVal = filter_var($value, FILTER_VALIDATE_INT);

            if ($intVal === false) {
                throw new Exception($message);
            }

            return $intVal;
        };
    }

    /**
     * Returns a closure that casts input to either int or float.
     *
     * @param string $message Error message if cast fails.
     * @return Closure(string): int|float|null
     */
    public function numeric(string $message = ""): Closure
    {
        return function (string $input) use ($message): int|float|null {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = $this->normalizeNumber($input, $message);
            $intVal = filter_var($value, FILTER_VALIDATE_INT);

            if ($intVal === false) {
                return $intVal;
            }

            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($floatVal === false) {
                return $floatVal;
            }

            throw new Exception($message);
        };
    }

    /**
     * Returns a closure that optionally trims input and returns it as a string.
     *
     * @param bool $trim If true, trims whitespace.
     * @return Closure(string): string
     */
    public function string(bool $trim = false): Closure
    {
        return function (string $input) use ($trim): string {
            if ($trim) {
                return trim($input);
            }

            return $input;
        };
    }

    /**
     * Returns null for empty strings (after trimming), otherwise returns the string.
     *
     * @param string $input Input string.
     * @return mixed|null Original string or null.
     * @internal
     */
    protected function nullOnEmpty(string $input): mixed
    {
        if (trim($input) === "") {
            return null;
        }

        return $input;
    }

    /**
     * Extracts and returns the first numeric pattern from input string.
     *
     * @param string $input Raw input string.
     * @param string $message Error to throw if no valid number found.
     * @return string Extracted numeric string.
     * @throws Exception If no valid number pattern is found.
     * @internal
     */
    protected function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);

        if (empty($matches[0])) {
            throw new Exception($message);
        }

        return $matches[0][0];
    }
}
