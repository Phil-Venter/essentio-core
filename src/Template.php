<?php

namespace Essentio\Core;

class Template
{
    protected array $segments = [];

    protected ?self $layout = null;

    protected array $stack = [];

    public function __construct(protected ?string $template = null) {}

    /**
     * Set a parent layout template.
     *
     * @param string $template
     * @return void
     */
    protected function layout(string $template): void
    {
        $this->layout = new static($template);
    }

    /**
     * Get the content of a named segment.
     *
     * @param string $name
     * @return string|null
     */
    protected function yield(string $name): ?string
    {
        return $this->segments[$name] ?? null;
    }

    /**
     * Start or set a segment's content.
     *
     * @param string $name
     * @param string|null $value
     * @return void
     */
    protected function segment(string $name, ?string $value = null): void
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
     *
     * @return void
     */
    protected function end(): void
    {
        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    /**
     * Render the view and optional layout.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function render(array $data = []): string
    {
        $content = (function (array $data) {
            ob_start();
            extract($data);
            include $this->template;
            return ob_get_clean();
        })($data);

        if ($this->layout !== null) {
            $this->segments["content"] = $content;
            $this->layout->segments = $this->segments;
            return $this->layout->render();
        }

        return $content;
    }
}
