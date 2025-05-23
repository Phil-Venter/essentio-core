<?php

namespace Essentio\Core\Extra;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

class Cast
{
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

    public function string(bool $trim = false): Closure
    {
        return function (string $input) use ($trim): string {
            if ($trim) {
                return trim($input);
            }

            return $input;
        };
    }

    protected function nullOnEmpty(string $input): mixed
    {
        if (trim($input) === "") {
            return null;
        }

        return $input;
    }

    protected function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);

        if (empty($matches[0])) {
            throw new Exception($message);
        }

        return $matches[0][0];
    }
}
