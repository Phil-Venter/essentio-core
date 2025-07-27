<?php

use Essentio\Web\Template;

function createTemplate(string $content): string
{
    $file = tempnam(sys_get_temp_dir(), 'tpl_');
    file_put_contents($file, $content);
    return $file;
}

it('renders a simple template with data', function () {
    $view = createTemplate('<p>Hello <?= $name ?></p>');

    $tpl = new Template($view);
    $output = $tpl->render(['name' => 'World']);

    expect($output)->toBe('<p>Hello World</p>');

    unlink($view);
});

it('renders a template using a layout', function () {
    $layout = createTemplate('<html><body><?= $this->yield("content") ?></body></html>');
    $viewContent = "<?php \$this->layout('$layout'); ?>\n<p>Hello <?= \$name ?></p>";
    $view = createTemplate($viewContent);

    $tpl = new Template($view);
    $output = $tpl->render(['name' => 'Jane']);

    expect($output)->toBe('<html><body><p>Hello Jane</p></body></html>');

    unlink($layout);
    unlink($view);
});

it('supports nested layouts and segment stacking', function () {
    $base = createTemplate('<html><head><title><?= $this->yield("title") ?></title></head><body><?= $this->yield("body") ?></body></html>');
    $mid  = createTemplate("<?php \$this->layout('$base'); ?><?php \$this->segment('body'); ?><header><?= \$this->yield('header') ?></header><?= \$this->yield('content') ?><footer><?= \$this->yield('footer') ?></footer><?php \$this->end(); ?>");
    $view = createTemplate("<?php \$this->layout('$mid'); \$this->segment('title'); ?>Page<?php \$this->end(); \$this->segment('header'); ?>H<?php \$this->end(); \$this->segment('footer'); ?>F<?php \$this->end(); ?>Hello");

    $tpl = new Template($view);
    $output = $tpl->render();

    expect($output)->toBe('<html><head><title>Page</title></head><body><header>H</header>Hello<footer>F</footer></body></html>');

    unlink($base);
    unlink($mid);
    unlink($view);
});
