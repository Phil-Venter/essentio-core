<?php

namespace Essentio\Core;

class Template
{
    public function __construct(
        public ?string $template = null,
        public array $segments = [],
        public ?self $layout = null,
        public array $stack = []
    ) {}

    protected function layout(string $template): void
    {
        $this->layout = new static($template);
    }

    protected function yield(string $name, string $default = ""): string
    {
        return $this->segments[$name] ?? $default;
    }

    protected function segment(string $name, ?string $value = null): void
    {
        if (func_num_args() === 2) {
            $this->segments[$name] = $value;
        } else {
            $this->stack[] = $name;
            ob_start();
        }
    }

    protected function end(): void
    {
        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

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
            $this->layout->setSegments($this->segments);
            return $this->layout->render($data);
        }

        return $content;
    }

    protected function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }
}
