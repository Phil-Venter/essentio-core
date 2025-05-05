<?php

namespace Essentio\Core\Extra;

use LogicException;
use RuntimeException;

use function array_pop;
use function extract;
use function file_exists;
use function ob_get_clean;
use function ob_start;

class Template
{
    /** @var ?self */
    protected ?self $layout = null;

    /** @var list<string> */
    protected array $stack = [];

    /** @var array<string,string> */
    protected array $segments = [];

    /**
     * @param mixed $path
     */
    public function __construct(protected ?string $path = null) {}

    /**
     * Sets the layout template to be used for rendering.
     *
     * @param string $path
     * @return void
     */
    protected function layout(string $path): void
    {
        $this->layout = new Template($path);
    }

    /**
     * Retrieves the content of a named segment or returns a default string.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    protected function yield(string $name, string $default = ""): string
    {
        return $this->segments[$name] ?? $default;
    }

    /**
     * Starts or sets a named content segment.
     *
     * @param string      $name
     * @param string|null $value
     * @return void
     */
    protected function segment(string $name, ?string $value = null): void
    {
        if ($value !== null) {
            $this->segments[$name] = $value;
        } else {
            $this->stack[] = $name;
            ob_start();
        }
    }

    /**
     * Ends the current output buffer and assigns it to the last opened segment.
     *
     * @return void
     * @throws LogicException if no segment is open
     */
    protected function end(): void
    {
        if (empty($this->stack)) {
            throw new LogicException("No segment is currently open.");
        }

        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    /**
     * Renders the template and returns the resulting HTML string.
     *
     * @param array<string,mixed> $data
     * @return string
     */
    public function render(array $data = []): string
    {
        if (!$this->path || !file_exists($this->path)) {
            throw new RuntimeException(sprintf("Template [%s] does not exist.", $this->path));
        }

        $content = (function (array $data) {
            ob_start();
            extract($data);
            include $this->path;
            return ob_get_clean();
        })($data);

        if ($this->layout !== null) {
            $this->segments["content"] = $content;
            $this->layout->setSegments($this->segments);
            return $this->layout->render($data);
        }

        return $content;
    }

    /**
     * Sets the segment content to be used when rendering the layout.
     *
     * @param array<string,string> $segments
     * @return void
     * @internal
     */
    public function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }
}
