# Essentio — Minimalist PHP Framework

Essentio isn’t here to impress with design patterns or win internet debates. It’s raw, minimal PHP—for developers who want clarity, speed, and control. No abstractions, no ceremony. Just the essentials.

You can learn the whole framework in an afternoon. That’s the point.

---

## 🔥 Philosophy

Essentio exists because modern PHP frameworks lost the plot.

Somewhere along the way, we decided every project needs hundreds of dependencies, a build chain, and layers of abstraction just to respond to a request. The result? Bloat, boilerplate, and a constant sense that you're working around your tools instead of with them.

Essentio is built around simpler questions:

* **Why can’t I just write code and have it run?**
* **Why does “Hello World” pull in a hundred packages?**
* **Do frameworks need to be opinionated—or just useful?**
* **Why all the scaffolding, code generation, and ceremony?**
* **What if I could learn the entire framework in a single afternoon?**

This isn’t for everyone. It’s for developers who want full control without the hand-holding. Who trust their own judgment more than someone else’s defaults. Who read the source instead of tutorials.

**Essentio doesn’t try to teach you how to code. It gives you just enough structure to be useful—and nothing that gets in your way.**

---

## 🧪 Quickstart

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

Application::http(__DIR__ . '/..');

get('/', fn() => text('Hello, Essentio!'));

Application::run();
EOF

php -S localhost:8080 -t public
```

---

### Composer Install

For projects using Composer (or if you prefer that):

```bash
composer require essentio/core
```

```php
use Essentio\Core\Application;

require __DIR__ . '/../vendor/autoload.php';

Application::http(__DIR__ . '/..');

get('/', fn() => text('Hello, Essentio!'));

Application::run();
```

---

## 🧱 Features

Essentio gives you what PHP left out—just enough to build apps with precision and no nonsense:

* **Boots fast**: HTTP or CLI mode with one line.
* **Minimal router**: Params, groups, middleware—no fluff.
* **Manual DI**: Bind what you want, resolve it yourself.
* **Environment-aware**: Typed `.env` loading—no YAML.
* **Input-safe**: Sanitize and validate with zero magic.
* **Session + CSRF**: Straightforward, not silent.
* **Templating**: Clean segments and blocks. No DSLs.
* **Explicit responses**: `json()`, `text()`, `html()`—your call.

---

## 🧍 Who It’s For

Essentio is for developers who:

* Want to understand every line
* Don’t need training wheels
* Trust code over convention
* Want fewer files, fewer surprises, and fewer opinions

Whether you’re building tools, APIs, internal apps, or microservices—Essentio gives you a sharp knife and walks away.

---

## 🧠 What You Get

### 🧩 Core (`base.php` and `full.php`)

#### Classes

| Class           | Purpose                        |
| --------------- | ------------------------------ |
| `Application`   | Starts HTTP/CLI lifecycle      |
| `Container`     | Bindings, singleton resolution |
| `Router`        | Routes + middleware            |
| `Request`       | Unified input                  |
| `Response`      | Output handler                 |
| `Session`       | Session + flash + CSRF         |
| `Template`      | PHP layout segments            |
| `Jwt`           | Stateless JWT handling         |
| `Environment`   | Typed `.env` loader            |
| `HttpException` | HTTP errors                    |
| `Cast`          | Input coercion                 |
| `Validate`      | Input rules                    |

#### Global Helpers

| Function                                          | Purpose               |
| ------------------------------------------------- | --------------------- |
| `get()`, `post()`, `put()`, `patch()`, `delete()` | Routing               |
| `input()`, `request()`                            | Request values        |
| `sanitize()`                                      | Validate + cast input |
| `env()`                                           | Config values         |
| `session()`, `flash()`                            | Session state         |
| `csrf()`                                          | CSRF token utils      |
| `render()`, `view()`                              | Templates             |
| `json()`, `text()`, `html()`, `redirect()`        | Responses             |
| `throw_if()`                                      | Assert/throw shortcut |
| `app()`, `bind()`, `once()`, `map()`              | DI                    |
| `base()`                                          | Base path resolver    |

---

### ➕ Extras (`full.php` only)

#### Extra Classes

| Class      | Purpose            |
| ---------- | ------------------ |
| `Query`    | Fluent SQL builder |
| `Argument` | CLI arg parser     |

#### Extra Global Helpers

| Function    | Purpose                |
| ----------- | ---------------------- |
| `command()` | Define CLI commands    |
| `arg()`     | Get CLI arguments      |
| `query()`   | SQL builder entrypoint |

---

## 🧾 Example App

```php
Application::http(__DIR__);

get('/', fn() =>
    view(base('template/home.tmpl.php'), [
        'name' => input('name', 'Guest')
    ])
);

post('/submit', function () {
    $data = sanitize([
        'email' => [
            Validate::required('Email address is required.'),
            Validate::email('Invalid email address.')
        ],
        'age' => [
            Cast::int('Not an int'),
            Validate::gte(18, 'Not old enough.')
        ]
    ]);

    if ($data === false) {
        return json(['errors' => request()->errors], 422);
    }

    return json($data);
});

Application::run();
```

---

## 🧪 What It Doesn’t Do

Essentio doesn’t care about:

* ❌ Autowiring
* ❌ Scaffolding
* ❌ ORM
* ❌ Code generation
* ❌ File structure
* ❌ Layered abstraction

If it happens, it’s because you wrote it.

---

## 🧮 Code Size

Measured using [cloc](https://github.com/AlDanial/cloc):

**Base:**

```
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                              1            157             17            703
-------------------------------------------------------------------------------
```

**Full (with Extras):**

```
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                              1            356             18           1585
-------------------------------------------------------------------------------
```

---

## 🪪 License

Essentio is licensed under the [0BSD License](https://opensource.org/licenses/0BSD). No conditions. No attribution. No nonsense.

Use it. Fork it. Rip it apart. Whatever helps you ship.

---

> Essentio is yours to love, hate, or ignore. The world won’t always agree—but that’s not your problem.
