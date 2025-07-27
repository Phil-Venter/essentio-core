<?php

namespace Essentio\Web;

use Essentio\FrameworkException;

/**
 * @api
 */
class Template
{
    protected array $segments = [];

    protected ?self $layout = null;

    protected array $stack = [];

    public function __construct(protected readonly string $template) {}

    /**
     * Set a parent layout template.
     */
    public function layout(string $template): void
    {
        $this->layout = new static($template);
    }

    /**
     * Get the content of a named segment.
     */
    public function yield(string $name): ?string
    {
        return $this->segments[$name] ?? null;
    }

    /**
     * Start or set a segment's content.
     */
    public function segment(string $name, ?string $value = null): void
    {
        if ($value === null) {
            $this->stack[] = $name;
            ob_start();
        } else {
            $this->segments[$name] = $value;
        }
    }

    /**
     * End the current segment buffer.
     */
    public function end(): void
    {
        if ($this->stack === []) {
            throw new FrameworkException("No segment started");
        }

        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    /**
     * Render the view and optional layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(array $data = []): string
    {
        if (!file_exists($this->template)) {
            throw new FrameworkException("Template file not found");
        }

        $content =
            (function (array $data): string|false {
                ob_start();
                extract($data);
                include $this->template;
                return ob_get_clean();
            })($data) ?:
            "";

        if ($this->layout instanceof static) {
            $this->segments["content"] = $content;
            $this->layout->segments = $this->segments;
            return $this->layout->render();
        }

        return $content;
    }
}
