# Essentio — Minimalist PHP Framework

Essentio isn’t here to impress with design patterns or win internet debates. It’s raw, minimal PHP—for developers who want clarity, speed, and control. No abstractions, no ceremony. Just the essentials.

---

## Quickstart

### One-file Setup

No dependencies. No build steps. Just download and go:

```bash
# Full version with extras
curl -L https://raw.githubusercontent.com/Phil-Venter/essentio_core/main/dist/full.php -o framework.php

# Base version, leanest setup
curl -L https://raw.githubusercontent.com/Phil-Venter/essentio_core/main/dist/base.php -o framework.php
```

Then scaffold a minimal app:

```bash
mkdir public

cat <<'EOF' > public/index.php
<?php

require __DIR__ . '/../framework.php';

Application::web(__DIR__ . '/..');

get('/', fn() => text('Hello, Essentio!'));

Application::run();
EOF

php -S localhost:8080 -t public
```

### Composer Install

For projects using Composer (or if you prefer that):

```bash
composer require essentio/core
```

Create your entry point:

```bash
mkdir public

cat <<'EOF' > public/index.php
<?php

use Essentio\Core\Application;

require __DIR__ . '/../vendor/autoload.php';

Application::web(__DIR__ . '/..');

get('/', fn() => text('Hello, Essentio!'));

Application::run();
EOF

php -S localhost:8080 -t public
```

---

## Why Essentio?

Essentio is built for developers who prefer simplicity over convention and explicitness over magic. It strips away the layers so you can get to work—fast.

No opinionated file structures, no hidden scaffolding, no startup delay. Just minimal tools that get out of your way. If something doesn’t suit you: ignore it, change it, or send a PR. It’s all yours.

---

## Features

Essentio gives you just enough to build—from simple tools to full apps:

* **Bootstrap fast**: Start a CLI or HTTP app in one call.
* **Lightweight router**: Groups, params, and middleware with zero fluff.
* **Manual DI container**: Bind what you need. Nothing resolves unless you say so.
* **Environment loader**: Typed `.env` parsing without YAML overhead.
* **Request/Response handling**: No magic, just PHP.
* **Sessions & flash**: Behaves as expected. No guesswork.
* **Helper functions**: `get()`, `input()`, `env()`, `redirect()`, `dump()`—use them freely.

### Optional Extras

Use them if they help. Leave them out if they don’t.

* **Cast** – Type-cast input to bools, dates, enums, numbers, etc.
* **Validate** – Closure-based input validation with built-in and custom rules.
* **Query** – Fluent SQL builder for PDO. No ORM. No migrations.
* **Template** – Minimal template engine with layout support, no DSL.
* **Mailer** – Basic email sending via SMTP and cURL.

---

## Who It’s For

Essentio is for developers who don’t need a framework to teach them PHP. It’s for those who’d rather write the code than configure it, who don’t want a generator holding their hand, and who understand that less code means fewer surprises. Whether you're building small tools, internal APIs, command-line apps, or just want a clean slate—Essentio gives you a sharp knife and stays out of the way.

---

## What Essentio Does Not Care About

Essentio doesn’t care about best practices, architectural purity, or what some blog thinks is idiomatic. No autowiring, no reflection, no scaffolding, no conventions. It won’t hold your hand, structure your app, or second-guess your intent. If it breaks, it breaks—deal with it. Write procedural code, imperative code, or whatever gets the job done. Essentio stays out of your way.

---

## Customizing

This isn’t a black box. Modify whatever you like—add middleware, tweak error handling, extend or replace components. It’s small on purpose.

---

## Code Size

Measured using [cloc](https://github.com/AlDanial/cloc):

**Base:**

```
PHP | 147 blanks | 5 comments | 649 code lines
```

**Full (with Extras):**

```
PHP | 336 blanks | 241 comments | 1455 code lines
```

---

## License

Essentio is licensed under the [0BSD License](https://opensource.org/licenses/0BSD). No conditions, no attribution, no nonsense.

Like something? Take it.
Use it, fork it, break it, fix it—whatever helps you ship.

---

> Essentio is yours to love, hate, or improve. The world won’t always agree—but that’s not your problem.
