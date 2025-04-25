<?php

use Essentio\Core\Template;

class TestableTemplate extends Template
{
    public function exposeLayout(string $path): void
    {
        $this->layout($path);
    }
    public function exposeSegment(string $name, ?string $value = null): void
    {
        $this->segment($name, $value);
    }
    public function exposeEnd(): void
    {
        $this->end();
    }
    public function exposeYield(string $name, string $default = ""): string
    {
        return $this->yield($name, $default);
    }
}

describe(Template::class, function (): void {
    it("yield returns default when segment is not set", function (): void {
        $template = new TestableTemplate();
        expect($template->exposeYield("nonexistent", "default"))->toBe("default");
    });

    it("sets a segment directly when a value is provided", function (): void {
        $template = new TestableTemplate();
        $template->exposeSegment("title", "Test Title");
        expect($template->exposeYield("title"))->toBe("Test Title");
    });

    it("captures buffered output in a segment", function (): void {
        $template = new TestableTemplate();
        $template->exposeSegment("content");
        echo "Buffered Content";
        $template->exposeEnd();
        expect($template->exposeYield("content"))->toBe("Buffered Content");
    });

    it("throws exception if end() is called without an open segment", function (): void {
        $template = new TestableTemplate();
        expect(fn() => $template->exposeEnd())->toThrow(LogicException::class, "No segment is currently open.");
    });

    it("renders a template file without layout", function (): void {
        $file = tempnam(sys_get_temp_dir(), "tmpl");
        file_put_contents($file, '<?php echo "Hello, " . $name . "!";');

        $template = new TestableTemplate($file);
        $output = $template->render(["name" => "World"]);

        unlink($file);

        expect($output)->toBe("Hello, World!");
    });

    it("renders a template with a layout", function (): void {
        $childFile = tempnam(sys_get_temp_dir(), "child");
        file_put_contents($childFile, '<?php echo "Child Content";');

        $layoutFile = tempnam(sys_get_temp_dir(), "layout");
        file_put_contents($layoutFile, '<?php echo "Header - " . $this->yield("content") . " - Footer";');

        $template = new TestableTemplate($childFile);
        $template->exposeLayout($layoutFile);
        $output = $template->render();

        unlink($childFile);
        unlink($layoutFile);

        expect($output)->toBe("Header - Child Content - Footer");
    });
});
