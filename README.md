# Essentio — Minimalist PHP Framework

Essentio is not designed to impress you with shiny best practices, trendy methodologies, or the approval of coding gurus. This is raw, minimal PHP built strictly for those who want simplicity, speed, and direct control—no more, no less.

## Why Essentio?

Because sometimes you don't want the overhead. You don't need the dogma. You're tired of the "one size fits all" frameworks loaded with unnecessary features. Essentio is intentionally stripped down to just what is essential for bootstrapping small PHP projects, both for CLI and web.

If you see something here that you don't like, that's fine. You have two options:

- **Don't use it.** Seriously, there are plenty of bloated, convention-riddled alternatives out there.
- **Change it yourself.** Essentio is less than 700 lines of pure, straightforward PHP (excluding comments). It won't bite. If you want something improved, send a pull request. Pull requests speak louder than bug reports.

## What Essentio Gives You

- Simple and explicit initialization for web or CLI.
- Minimalistic routing without convoluted abstractions.
- Lightweight dependency injection container with zero magic.
- Basic configuration and environment management.
- Simple, understandable session management.
- Clean and straightforward HTTP request and response handling.
- Essential utility functions (config, env, logging, etc.) without the noise.

## What Essentio Does Not Care About

- Following every single best practice recommended by PHP influencers.
- Catering to complex edge cases or enterprise-level convolutions.
- Pleasing everyone.

## Quickstart

### One file wonder

I have been enamored with the idea of just uploading a single php file to your server and calling it a day.
So that's what I attempted to do, you can copy `dist/index.php` to your project root and start coding at the end of it.

NOTE: If something is in `src/` is missing from `dist/index.php` you can compile it anew with `composer run-script build`.

### Composer

You can also install this package via composer: `composer require essentio/core`.

### Web Initialization

```php
Application::http(__DIR__);

route('GET', 'ping', fn() => text('PONG'));

Application::run();
```

### CLI Initialization

```php
Application::cli(__DIR__);

command('ping', fn () => cli_log('PONG'));
```

## Customization & Extending

It's deliberately small—extend it yourself. Add your middleware, improve error handling, or replace components entirely. Fork it, mold it to your project, or just tweak what irritates you.

Essentio is a base, not a cage.

## License

MIT License. Freedom to use, freedom to change, freedom to ignore.

---

Essentio is yours to love, hate, or improve. The world won't always agree—but that's not your problem.
