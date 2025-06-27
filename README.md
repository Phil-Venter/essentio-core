![License](https://img.shields.io/badge/license-0BSD-lightgrey)
![PHP](https://img.shields.io/badge/php-%3E%3D8.2-blue)
![CI](https://github.com/Phil-Venter/essentio-core/actions/workflows/php.yml/badge.svg)
![Last Commit](https://img.shields.io/github/last-commit/Phil-Venter/essentio-core)
![Stars](https://img.shields.io/github/stars/Phil-Venter/essentio-core?style=social)

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
curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/full.php -o framework.php

# Base version, leanest setup
curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/base.php -o framework.php
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

```bash
mkdir public

cat <<'EOF' > public/index.php
<?php

require __DIR__ . '/../vendor/autoload.php';

Essentio\Core\Application::http(__DIR__ . '/..');

get('/', fn() => text('Hello, Essentio!'));

Essentio\Core\Application::run();
EOF

php -S localhost:8080 -t public
```

---

## 🧱 Features

Essentio gives you just enough to build apps with precision and no nonsense:

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

### 🧩 Core Components (`base.php` & `full.php`)

#### 📦 Classes

| Class           | Description                              |
| --------------- | ---------------------------------------- |
| `Application`   | Initializes HTTP or CLI context          |
| `Container`     | Dependency injection container           |
| `Router`        | Route registry and dispatcher            |
| `Request`       | Normalized HTTP request abstraction      |
| `Response`      | Fluent HTTP response builder             |
| `Session`       | Native session management + CSRF support |
| `Template`      | Layout/segment PHP-based template engine |
| `Jwt`           | Stateless JWT encoder/decoder            |
| `Environment`   | `.env` file parser with auto-casting     |
| `HttpException` | Structured HTTP error generator          |

---

### 🌍 Global Helper Functions

#### 🧱 Dependency Injection

| Function           | Description                   |
| ------------------ | ----------------------------- |
| `app(class)`       | Get a singleton instance      |
| `map(class, deps)` | Create a new instance         |
| `bind(class, ...)` | Register service (multi-call) |
| `once(class, ...)` | Register singleton (one-time) |

#### ⚙️ Environment & Paths

| Function          | Description                 |
| ----------------- | --------------------------- |
| `base_path(path)` | Join path to base directory |
| `env(key)`        | Retrieve `.env` variable    |

#### 🌐 Routing & Requests

| Function                                    | Description                       |
| ------------------------------------------- | --------------------------------- |
| `middleware(fn)`                            | Global middleware registration    |
| `group(prefix, fn)`                         | Grouped routes under a prefix     |
| `get(path, fn)` `post(path, fn)` `put()`... | Route registration for HTTP verbs |
| `request(key)`<br>`input(field)`            | Access route/query/form input     |
| `sanitize(rules, onError)`                  | Cast + validate user input        |

#### 🗂️ Sessions & Security

| Function                 | Description                   |
| ------------------------ | ----------------------------- |
| `session(key)`           | Get/set session variable      |
| `flash(key)`             | Temporary one-request data    |
| `csrf()` / `csrf(token)` | Generate or verify CSRF token |
| `jwt(data)`              | Encode/Decode JWT payload     |

#### 📤 Responses

| Function                                | Description                    |
| --------------------------------------- | ------------------------------ |
| `render(template, data)`                | Render template to string      |
| `html(str)`, `text(str)`, `json(mixed)` | Send typed response content    |
| `redirect(uri, status)`                 | Issue HTTP redirect            |
| `view(template, data)`                  | Return templated HTML response |

#### 🛠️ Miscellaneous

| Function                 | Description                  |
| ------------------------ | ---------------------------- |
| `throw_if(cond, except)` | Conditionally throw an error |

---

### ➕ Extended Utilities (`full.php` only)

#### 📦 Additional Classes

| Class        | Description                            |
| ------------ | -------------------------------------- |
| `Cast`       | Input transformation (type coercion)   |
| `HttpClient` | Minimal HTTP client using cURL         |
| `Query`      | Fluent SQL query builder (PDO-based)   |
| `Validate`   | Validation rules (regex, bounds, etc.) |

#### 🌍 Additional Helpers

| Function                           | Description                                |
| ---------------------------------- | ------------------------------------------ |
| `http(method, url, headers, body)` | Send an HTTP request and return `Response` |
| `query()`                          | Instantiate `Query` object                 |

You need to explicitly bind the query builder to the container before using it.

```php
once(PDO::class, fn() => new PDO("sqlite:" . base_path("database.sqlite")));
bind(Query::class, fn() => new Query(app(PDO::class)));
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
PHP                              1            194            574            756
-------------------------------------------------------------------------------
```

**Full (with Extras):**
```
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                              1            375            887           1373
-------------------------------------------------------------------------------
```

**Pre build (src):**
```
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                             19            406            887           1449
-------------------------------------------------------------------------------
```

---

## 🪪 License

Essentio is licensed under the [0BSD License](https://opensource.org/licenses/0BSD). No conditions. No attribution. No nonsense.

Use it. Fork it. Rip it apart. Whatever helps you ship.

---

## 🤝 Contributing

Pull requests welcome. Ideas welcome. Opinions optional.

---

> Essentio is yours to love, hate, or ignore. The world won’t always agree—but that’s not your problem.
