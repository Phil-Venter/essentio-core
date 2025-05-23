# Essentio — Minimalist PHP Framework

Essentio is not designed to impress you with shiny best practices, trendy methodologies, or the approval of coding gurus. This is raw, minimal PHP built strictly for those who want simplicity, speed, and direct control—no more, no less.

## Why Essentio?

Because sometimes you don't want the overhead. You don't need the dogma. You're tired of the "one size fits all" frameworks loaded with unnecessary features. Essentio is intentionally stripped down to just what is essential for bootstrapping small PHP projects, both for CLI and web.

If you see something here that you don't like, that's fine. You have two options:

- **Don't use it.** Seriously, there are plenty of bloated, convention-riddled alternatives out there.
- **Change it yourself.** It's quite small. It won't bite. If you want something improved, send a pull request. Pull requests speak louder than bug reports.

## LoC

`dist/base.php`
```
github.com/AlDanial/cloc v 2.04
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                              1            175            426            676
-------------------------------------------------------------------------------
```

`dist/all.php`
```
github.com/AlDanial/cloc v 2.04
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                              1            393            683           1549
-------------------------------------------------------------------------------
```

## What Essentio Gives You

* **Dead-simple bootstrapping** for web or CLI—just call `Application::http()` or `::cli()` and go.
* A **tiny router** with zero ceremony—supports route grouping, parameterized paths, and optional middleware.
* A **barebones dependency injection container**—you bind what you need, no magic involved.
* **Environment variable loader** with type-casting that Just Works™—no 15-line YAML configs required.
* **Sessions** that behave like you'd expect. Flash data? Done.
* **Request/Response** handling that doesn't hide PHP under 5 layers of abstraction.
* Handy **global helpers** (`get()`, `input()`, `json()`, `env()`, `redirect()`, `dump()`, etc.) that don't make you feel bad for using them.

## Optional Extras

The base is enough. But if you want more, there are Extras. They're not enabled by default, not required, and not magical. Just code you can use—or ignore.

- **Cast** – Converts input into booleans, dates, enums, floats, and more. Throws if it fails.
- **Validate** – Rule-based input validation using closures. Write your own rules, or use the built-ins.
- **Query** – A fluent SQL builder for PDO. No ORM. No migrations. Just parameterized SQL you control.
- **Template** – A basic templating engine with layout support. No caching, no tags, no DSL.
- **Mailer** – Send plain text or HTML email using SMTP and cURL. Just enough for contact forms or alerts.

Use them if they save you time. Delete them if they don't.

## What Essentio Does Not Care About

* **"Best practices"™** as defined by internet influencers and committee-driven blog posts.
* **Framework purity tests.** If you're asking whether something is "idiomatic," you're probably in the wrong repo.
* **Convention over configuration.** Essentio doesn't guess what you mean—it does what you tell it.
* **Autowiring, reflection-based DI, service locators, containers inside containers.** You bind it, you get it.
* **Overabstracted error handling.** If it breaks, you’ll see it. If you want better logs, write them.
* **Codegen, scaffolding, or file-based voodoo.** Your filesystem is not your IDE.
* **Overengineering for hypothetical scale.** Designed for actual projects, not tech talks.
* **Making you feel bad for writing imperative code.** Procedural is not a sin.
* **Enterprise buzzwords.** No facades, service providers, factories, or adapters unless you write them.

Essentio isn’t here to teach you how to write PHP. It assumes you already know—or you’ll figure it out.

## Quickstart

### One file wonder

I have been enamored with the idea of just uploading a single php file to your server and calling it a day.
So that's what I attempted to do, you can run the command below in your project root and start coding at the end of it.

- **Full** (with extras like DB, Mail, Template, etc.): `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio_core/main/dist/all.php -o index.php`
- **Base only** (leanest version): `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio_core/main/dist/base.php -o index.php`

### Composer

You can also install this package via composer: `composer require essentio/core`.

### Initialization and Execution

Use `Application::http()` or `Application::cli()` to start your app, and `Application::run()` to process web requests.

Rely on the global functions for routing (`get()`, `post()`, etc.), service management (`app()`, `env()`, `bind()`), handling request data (`request()`, `input()`), generating responses (`redirect()`, `json()`, `text()`, `view()`), and performing utility operations.

## Customization & Extending

It's deliberately small—extend it yourself. Add your middleware, improve error handling, or replace components entirely. Fork it, mold it to your project, or just tweak what irritates you.

## License

MIT License. Freedom to use, freedom to change, freedom to ignore.

---

> Essentio is yours to love, hate, or improve. The world won't always agree—but that's not your problem.
