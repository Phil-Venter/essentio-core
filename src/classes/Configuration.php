<?php

namespace Essentio\Core;

/**
 * Manages application configuration by loading configuration files and
 * converting arrays into a flattened dot notation format.
 */
class Configuration
{
    /** @var array<string, mixed> */
    public protected(set) array $data = [];

    /**
     * Load configuration data from a PHP file.
     *
     * This method expects the file to return an array. The configuration
     * data is merged with any existing data and keys are transformed into
     * dot notation with an optional prefix.
     *
     * @param string $file
     * @param string $prefix
     * @return static
     */
    public function load(string $file, string $prefix = ''): static
    {
        if (!\file_exists($file)) {
            return $this;
        }

        $this->data = \array_merge(
            $this->data,
            $this->arrayToDotNotation(include $file, $prefix)
        );

        return $this;
    }

    /**
     * Retrieve a configuration value.
     *
     * Fetches a value from the configuration data using dot notation.
     * If the key is not found, the provided default value is returned.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Convert a multi-dimensional array into a dot notation array.
     *
     * Recursively flattens the provided array, using dot notation for nested keys.
     * An optional prefix can be applied to each key.
     *
     * @param array  $array
     * @param string $prefix
     * @return array
     */
    protected function arrayToDotNotation(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\is_array($value) && !empty($value)) {
                $result = \array_merge(
                    $result,
                    $this->arrayToDotNotation($value, $newKey)
                );
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
