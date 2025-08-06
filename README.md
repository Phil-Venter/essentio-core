![License](https://img.shields.io/badge/license-0BSD-lightgrey)
![PHP](https://img.shields.io/badge/php-%3E%3D8.2-blue)
![CI](https://github.com/Phil-Venter/essentio-core/actions/workflows/php.yml/badge.svg)
![Last Commit](https://img.shields.io/github/last-commit/Phil-Venter/essentio-core)
![Stars](https://img.shields.io/github/stars/Phil-Venter/essentio-core?style=social)

# Essentio — Minimalist PHP Framework [WIP]

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

Choose one of three approaches to pull in the framework, then bootstrap and run your application.

---

### 1. Single file (fastest)

Include a single PHP file containing only the modules you need. This is great for rapid prototypes or simple scripts.

**Available builds:**

| Component                               | base | base+ | cli | cli+ | http | http+ | api | api+ | web | web+ | full | full+ |
|-----------------------------------------|:----:|:-----:|:---:|:----:|:----:|:-----:|:---:|:----:|:---:|:----:|:----:|:-----:|
| Application<br>Container<br>Environment | ✓    | ✓     | ✓   | ✓    | ✓    | ✓     | ✓   | ✓    | ✓   | ✓    | ✓    | ✓     |
| Argument                                |      |       | ✓   | ✓    |      |       |     |      |     |      | ✓    | ✓     |
| Request<br>Response<br>Router<br>Route  |      |       |     |      | ✓    | ✓     | ✓   | ✓    | ✓   | ✓    | ✓    | ✓     |
| Jwt                                     |      |       |     |      |      |       | ✓   | ✓    |     |      | ✓    | ✓     |
| Session<br>Template                     |      |       |     |      |      |       |     |      | ✓   | ✓    | ✓    | ✓     |
| Cast<br>Validate                        |      |       |     |      |      | ✓     |     | ✓    |     | ✓    |      | ✓     |
| HttpClient<br>Query                     |      | ✓     |     | ✓    |      | ✓     |     | ✓    |     | ✓    |      | ✓     |

**Download:**

| Build | Download link                                                                                                  |
| ----- | -------------------------------------------------------------------------------------------------------------- |
| base  | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/base.php -o framework.php`      |
| base+ | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/base-plus.php -o framework.php` |
| cli   | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/cli.php -o framework.php`       |
| cli+  | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/cli-plus.php -o framework.php`  |
| http  | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/http.php -o framework.php`      |
| http+ | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/http-plus.php -o framework.php` |
| api   | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/api.php -o framework.php`       |
| api+  | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/api-plus.php -o framework.php`  |
| web   | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/web.php -o framework.php`       |
| web+  | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/web-plus.php -o framework.php`  |
| full  | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/full.php -o framework.php`      |
| full+ | `curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/full-plus.php -o framework.php` |

**Bootstrap + Web Entry:** create public/index.php in the project root:

```php
<?php

require_once __DIR__ . '/../framework.php';

Application::http(__DIR__);
get('__ping', fn() => text('pong'));
Application::run();
```

---

### 2. Download into src/ (recommended)

Copy the framework files into a src/ folder for full ownership without Composer.

**Init:**

```bash
mkdir <project>
cd <project>
curl -s https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/bin/download.php | php
php cli serve
```

**Visit:** http://localhost:8000/__ping

---

### 3. Install via Composer (limited control)

Composer makes installation trivial, but you won’t have direct access to the raw framework files for customization.

```bash
composer require essentio/core
```

Then bootstrap and run exactly as above:

**Bootstrap + Web Entry:** create public/index.php in the project root:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

Essentio\Application::http(__DIR__);
get('__ping', fn() => text('pong'));
Essentio\Application::run();
```

> **Note:** Composer installs follow our release cycle. If you need to modify core behavior or patch bugs yourself, use the “Single file” or “Download into src/” methods instead.

---

## 🧱 Features

No ceremony. No surprises.

| Class                 | Description                                       |
| --------------------- | ------------------------------------------------- |
| `Application`         | Initializes HTTP or CLI context                   |
| `Container`           | Dependency injection container                    |
| `Router`              | Route registry and dispatcher                     |
| `Request`             | Normalized HTTP request abstraction               |
| `Response`            | Fluent HTTP response builder                      |
| `Session`             | Native session management + CSRF support          |
| `Template`            | Layout/segment PHP-based template engine          |
| `Jwt`                 | Stateless JWT encoder/decoder                     |
| `Environment`         | `.env` file parser with auto-casting              |
| `FrameworkException`  | Base exception type for internal framework errors |
| `ValidationException` | Thrown when validation rules fail                 |
| `HttpException`       | Structured HTTP error generator                   |
| `Cast`                | Input transformation (type coercion)              |
| `HttpClient`          | Minimal HTTP client using cURL                    |
| `Query`               | Fluent SQL query builder (PDO-based)              |
| `Validate`            | Validation rules (regex, bounds, etc.)            |

| Function                                                                                          | Description                                |
| ------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| `app(class)`                                                                                      | Get a singleton instance                   |
| `make(class, deps)`                                                                               | Create a new instance                      |
| `bind(class, ...)`                                                                                | Register service (multi-call)              |
| `once(class, ...)`                                                                                | Register singleton (one-time)              |
| `base_path(path)`                                                                                 | Join path to base directory                |
| `env(key)`                                                                                        | Retrieve `.env` variable                   |
| `middleware(fn)`                                                                                  | Global middleware registration             |
| `group(prefix, fn)`                                                                               | Grouped routes under a prefix              |
| `get(path, fn)`<br>`post(path, fn)`<br>`put(path, fn)`<br>`patch(path, fn)`<br>`delete(path, fn)` | Route registration for HTTP verbs          |
| `request(key)`<br>`input(field)`                                                                  | Access route/query/form input              |
| `sanitize(rules, onError)`                                                                        | Cast + validate user input                 |
| `session(key)`                                                                                    | Get/set session variable                   |
| `flash(key)`                                                                                      | Temporary one-request data                 |
| `csrf()` / `csrf(token)`                                                                          | Generate or verify CSRF token              |
| `jwt(data)`                                                                                       | Encode/Decode JWT payload                  |
| `e(scalar/Stringable)`                                                                            | Safely escapes a value for use in HTML     |
| `render(template, data)`                                                                          | Render template to string                  |
| `html(str)`<br>`text(str)`<br>`json(mixed)`                                                       | Send typed response content                |
| `redirect(uri, status)`                                                                           | Issue HTTP redirect                        |
| `view(template, data)`                                                                            | Return templated HTML response             |
| `throw_if(cond, except)`                                                                          | Conditionally throw an error               |
| `curl(method, url, headers, body)`                                                                | Send an HTTP request and return `Response` |
| `query()`                                                                                         | Instantiate `Query` object                 |

You need to explicitly bind the query builder to the container before using it.

```php
once(PDO::class, fn() => new PDO("sqlite:" . base_path("database.sqlite")));
bind(Query::class, fn() => new Query(app(PDO::class)));
```

---

## 🧍 Who It’s For

Essentio is for developers who:

* Want to understand every line
* Don’t need training wheels
* Trust code over convention
* Want fewer files, fewer surprises, and fewer opinions

Whether you’re building tools, APIs, internal apps, or microservices—Essentio gives you a sharp knife and walks away.

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

<!-- cloc -->
| FILE          | CODE | BLANK | COMMENT | TOTAL |
| ------------- | ---: | ----: | ------: | ----: |
| api-plus.php  | 1216 |   335 |     449 |  2000 |
| api.php       |  615 |   166 |     269 |  1050 |
| base-plus.php |  449 |   133 |     189 |   771 |
| base.php      |  167 |    49 |      95 |   311 |
| cli-plus.php  |  529 |   153 |     210 |   892 |
| cli.php       |  247 |    69 |     116 |   432 |
| full-plus.php | 1428 |   389 |     537 |  2354 |
| full.php      | 1146 |   305 |     443 |  1894 |
| http-plus.php | 1145 |   313 |     420 |  1878 |
| http.php      |  544 |   144 |     240 |   928 |
| web-plus.php  | 1277 |   347 |     487 |  2111 |
| web.php       |  676 |   178 |     307 |  1161 |
| src/*         | 1560 |   454 |     588 |  2602 |
<!-- ./cloc -->

---

## 🪪 License

Essentio is licensed under the [0BSD License](https://opensource.org/licenses/0BSD). No conditions. No attribution. No nonsense.

Use it. Fork it. Rip it apart. Whatever helps you ship.

---

## 🤝 Contributing

Pull requests welcome. Ideas welcome. Opinions optional.

---

> Essentio is yours to love, hate, or ignore. The world won’t always agree—but that’s not your problem.
