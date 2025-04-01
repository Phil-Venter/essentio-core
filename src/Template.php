<?php

namespace Essentio\Core;

use LogicException;

use function array_pop;
use function extract;
use function file_exists;
use function ob_get_clean;
use function ob_start;

class Template
{
    protected $layout = null;
    protected $stack = [];
    protected $segments = [];

    public function __construct(protected ?string $path = null) {}

    protected function layout(string $path): void
    {
        $this->layout = new Template($path);
    }

    protected function yield(string $name, string $default = ""): string
    {
        return $this->segments[$name] ?? $default;
    }

    protected function segment(string $name, ?string $value = null): void
    {
        if ($value !== null) {
            $this->segments[$name] = $value;
        } else {
            $this->stack[] = $name;
            ob_start();
        }
    }

    protected function end(): void
    {
        if (empty($this->stack)) {
            throw new LogicException("No segment is currently open.");
        }

        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    public function render(array $data = []): string
    {
        if ($this->path && file_exists($this->path)) {
            extract($data);
            ob_start();
            include $this->path;
            $this->segments["content"] = ob_get_clean();
        }

        if ($this->layout) {
            $this->layout->setSegments($this->segments);
            return $this->layout->render($data);
        }

        return $this->segments["content"];
    }

    public function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }
}
