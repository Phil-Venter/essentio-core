<?php

namespace Essentio\Core;

use function explode;
use function file;
use function file_exists;
use function is_numeric;
use function preg_match;
use function str_contains;
use function strtolower;
use function substr;
use function trim;

/**
 * Handles the loading and retrieval of environment variables from a file.
 * The file is parsed line-by-line, skipping comments and empty lines, while
 * converting the configuration into an associative array.
 */
class Environment
{
    /** @var array<string,mixed> */
    public protected(set) array $data = [];

    /**
     * Loads environment variables from a file.
     *
     * Reads a file line-by-line, ignoring lines that are empty or start with a '#'.
     * Each valid line is parsed into a key-value pair. Values are trimmed, unquoted if necessary,
     * and typecasted to boolean, null, or numeric values when applicable.
     *
     * @param string $file
     * @return static
     */
     public function load(string $file): static
	{
		if (!file_exists($file)) {
		    return $this;
		}

		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

		foreach ($lines as $line) {
		    if (trim($line)[0] === "#" || !str_contains($line, "=")) {
			    continue;
			}

		    [$name, $value] = explode("=", $line, 2);
		    $name = trim($name);
		    $value = trim($value);

		    if (preg_match('/^(["\']).*\1$/', $value)) {
		        $value = substr($value, 1, -1);
		    } else {
		        $lower = strtolower($value);
		        $value = match (true) {
		            $lower === "true"  => true,
		            $lower === "false" => false,
		            $lower === "null"  => null,
		            is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
		            default            => $value,
		        };
		    }

		    $this->data[$name] = $value;
		}

		return $this;
	}

    /**
     * Retrieves an environment variable.
     *
     * Returns the value of the specified environment variable from the loaded data.
     * If the variable is not found, the method returns the provided default value.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
