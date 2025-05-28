<?php

namespace Essentio\Core\Extra;

use Closure;
use DateTimeInterface;
use Exception;

class Validate
{
    /**
     * Allows alphabetic characters only.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function alpha(string $message = ""): Closure
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

    /**
     * Allows alphanumeric characters, underscores, and dashes.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function alphaDash(string $message = ""): Closure
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

    /**
     * Allows letters and numbers only.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function alphaNum(string $message = ""): Closure
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

    /**
     * Validates email address format.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function email(string $message = ""): Closure
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

    /**
     * Ensures input ends with one of the given suffixes.
     *
     * @param array $suffixes List of valid suffixes.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function endsWith(array $suffixes, string $message = ""): Closure
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

    /**
     * Validates input is entirely lowercase.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function lowercase(string $message = ""): Closure
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

    /**
     * Validates input is entirely uppercase.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function uppercase(string $message = ""): Closure
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

    /**
     * Enforces minimum character length.
     *
     * @param int $min Minimum allowed length.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function minLength(int $min, string $message = ""): Closure
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

    /**
     * Enforces maximum character length.
     *
     * @param int $max Maximum allowed length.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function maxLength(int $max, string $message = ""): Closure
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

    /**
     * Validates input against a regular expression pattern.
     *
     * @param string $pattern PCRE pattern to validate input.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function regex(string $pattern, string $message = ""): Closure
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

    /**
     * Ensures value is between two inclusive bounds.
     *
     * @param DateTimeInterface|float|int $min Lower bound.
     * @param DateTimeInterface|float|int $max Upper bound.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function between(
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

    /**
     * Ensures value is greater than given threshold.
     *
     * @param DateTimeInterface|float|int $min Minimum threshold (exclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function gt(DateTimeInterface|float|int $min, string $message = ""): Closure
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

    /**
     * Ensures value is greater than or equal to threshold.
     *
     * @param DateTimeInterface|float|int $min Minimum threshold (inclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function gte(DateTimeInterface|float|int $min, string $message = ""): Closure
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

    /**
     * Ensures value is less than given threshold.
     *
     * @param DateTimeInterface|float|int $max Maximum threshold (exclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function lt(DateTimeInterface|float|int $max, string $message = ""): Closure
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

    /**
     * Ensures value is less than or equal to threshold.
     *
     * @param DateTimeInterface|float|int $max Maximum threshold (inclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function lte(DateTimeInterface|float|int $max, string $message = ""): Closure
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

    /**
     * Ensures a value is not null or empty.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function required(string $message = ""): Closure
    {
        return function (mixed $input) use ($message): mixed {
            if (!isset($input) || (is_string($input) && trim($input) === "")) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Validates membership in a given array.
     *
     * @param array $allowed Valid values.
     * @param bool $strict Use strict type comparison.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function inArray(array $allowed, bool $strict = true, string $message = ""): Closure
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
