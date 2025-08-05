<?php

declare(strict_types=1);

use Essentio\Web\Session;
use Essentio\Web\Template;
use Essentio\Http\Response;

/**
 * Get or set a session value.
 */
function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

/**
 * Get or set a flash session value.
 */
function flash(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->getFlash($key) : app(Session::class)->setFlash($key, $value);
}

/**
 * Generate or verify a CSRF token.
 *
 * @template T as string
 * @param T $csrf
 * @return (T is '' ? string : bool)
 */
function csrf(string $csrf = ""): string|bool
{
    return func_num_args() !== 0 ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

/**
 * Escapes a value for safe use in HTML.
 *
 * @param scalar $val
 * @throws InvalidArgumentException
 */
function e(mixed $val): string
{
    if (is_scalar($val) || ($val instanceof Stringable)) {
        return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    throw new InvalidArgumentException('Invalid value type');
}

/**
 * Render a PHP template to string.
 *
 * @param array<string,mixed> $data
 */
function render(string $template, array $data = []): string
{
    return make(Template::class, [$template])->render($data);
}

/**
 * Render a view and return an HTML response.
 *
 * @param array<string,mixed> $data
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}
