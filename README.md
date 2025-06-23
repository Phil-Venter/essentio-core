![License](https://img.shields.io/badge/license-0BSD-lightgrey)
![PHP](https://img.shields.io/badge/php-%3E%3D8.1-blue)

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

#### 📦 Classes

| Class           | Purpose                        |
| --------------- | ------------------------------ |
| `Application`   | Bootstaps HTTP/CLI lifecycle   |
| `Container`     | Bindings, singleton resolution |
| `Router`        | Routes + middleware            |
| `Route`         | Router leaf node               |
| `Request`       | Unified input                  |
| `Response`      | Output handler                 |
| `Session`       | Session + flash + CSRF         |
| `Template`      | PHP layout segments            |
| `Jwt`           | Stateless JWT handling         |
| `Environment`   | Typed `.env` loader            |
| `HttpException` | HTTP errors                    |

### 🌍 Global Helpers

#### 🧱 Dependency Injection

| Function              | Purpose               |
| --------------------- | --------------------- |
| `app()`, `map()`      | Resolve services      |
| `bind()`, `once()`    | Register services     |

#### ⚙️ Environment & Path

| Function     | Purpose             |
| ------------ | ------------------- |
| `base()`     | Base path resolver  |
| `env()`      | Environment values  |

#### 🌐 Routing & Requests

| Function                                          | Purpose               |
| ------------------------------------------------- | --------------------- |
| `get()`, `post()`, `put()`, `patch()`, `delete()` | Route registration    |
| `request()`, `input()`                            | Access input values   |
| `sanitize()`                                      | Validate + cast input |

#### 🗂️ Session & Protection

| Function              | Purpose              |
| --------------------- | -------------------- |
| `session()`, `flash()`| Session state        |
| `csrf()`              | CSRF token utilities |
| `jwt()`               | JWT encode/decode    |

#### 📤 Response Helpers

| Function                           | Purpose         |
| ---------------------------------- | --------------- |
| `render()`                         | Render template |
| `json()`, `text()`, `html()`       | Send responses  |
| `redirect()`                       | Issue redirect  |
| `view()`                           | Template view   |

#### 🛠️ Utilities

| Function        | Purpose              |
| --------------- | -------------------- |
| `throw_if()`    | Conditional throw    |

### ➕ Extras (`full.php` only)

#### 📦 Extra Classes

| Class      | Purpose                           |
| ---------- | --------------------------------- |
| `Cast`     | Input casting for sanitization    |
| `Query`    | Fluent SQL builder                |
| `Validate` | Declarative validation rules      |

#### 🌍 Extra Global Helpers

| Function    | Purpose                |
| ----------- | ---------------------- |
| `query()`   | SQL builder entrypoint |

You need to explicitly bind the query builder to the container before using it.

```php
once(PDO::class, fn() => new PDO("sqlite:" . base("database.sqlite")));
bind(Query::class, fn() => new Query(app(PDO::class)));
```

---

## 🧾 Example App

A simple starting point for more advanced apps

```bash
curl -L https://raw.githubusercontent.com/Phil-Venter/essentio-core/main/dist/full.php -o framework.php
```

`public/index.php`
```php
require_once __DIR__ . '/../framework.php';

Application::http(__DIR__ . '/..');

require_once base('bootstrap.php');
require_once base('app.php');

Application::run();
```

`app.php`
```php
get("/assets/:file", function (Request $req) {
    $file = base("assets/" . basename($req->get("file")));

    if (!is_file($file)) {
        throw HttpException::create(404, "Asset not found.");
    }

    $lastModified = gmdate("D, d M Y H:i:s", filemtime($file)) . " GMT";
    $etag = '"' . md5_file($file) . '"';

    $ifModifiedSince = $_SERVER["HTTP_IF_MODIFIED_SINCE"] ?? null;
    $ifNoneMatch = $_SERVER["HTTP_IF_NONE_MATCH"] ?? null;

    if ($ifModifiedSince === $lastModified || $ifNoneMatch === $etag) {
        return app(Response::class)->setStatus(304);
    }

    return app(Response::class)
        ->addHeaders([
            "Content-Type" => mime_content_type($file),
            "Last-Modified" => $lastModified,
            "ETag" => $etag,
            "Cache-Control" => "public, max-age=31536000",
        ])
        ->setBody(file_get_contents($file));
});
```

`bootstrap.php`
```php
# CONTAINER
once(PDO::class, fn() => new PDO("sqlite:" . base("database.sqlite")));
bind(Query::class, fn() => new Query(app(PDO::class)));

# ERROR LOGGING MIDDLEWARE
middleware(function (Request $req, $next) {
    try {
        return $next($req);
    } catch (Throwable $e) {
        foreach ($e->getTrace() as $frame) {
            if (isset($frame["file"]) && !str_contains($frame["file"], "framework.php") && !str_contains($frame["file"], "bootstrap.php")) {
                $file = $frame["file"];
                $line = $frame["line"];
                break;
            }
        }

        $file ??= $e->getFile();
        $line ??= $e->getLine();

        error_log("[{$req->method}] /{$req->path} - {$e->getMessage()} {$file}:{$line}");
        throw $e;
    }
});

# CSRF MIDDLEWARE
middleware(function (Request $req, $next) {
    if (
        explode(";", $req->headers["Content-Type"] ?? "", 2)[0] !== "application/json" &&
        in_array($req->method, ["POST", "PUT", "PATCH", "DELETE"]) &&
        !csrf(input("_csrf") ?? ($req->headers["X-CSRF-TOKEN"] ?? ""))
    ) {
        throw HttpException::create(403, "CSRF token mismatch");
    }

    return $next($req);
});

# JWT MIDDLEWARE ON api/
middleware(function (Request $req, $next) {
    if (explode(";", $req->headers["Content-Type"] ?? "", 2)[0] === "application/json" && str_starts_with($req->path, "api/")) {
        try {
            jwt(trim(str_replace("Bearer ", "", $req->headers["Authorization"] ?? "")));
        } catch (Throwable) {
            throw HttpException::create(401, "Unauthorized");
        }

        return app(Response::class)->addHeaders(["Content-Type" => "application/json"]);
    }

    return $next($req);
});

# SECURITY HEADERS
middleware(function (Request $req, $next) {
    return $next($req)->addHeaders([
        "X-Content-Type-Options" => "nosniff",
        "X-Frame-Options" => "DENY",
        "X-XSS-Protection" => "1; mode=block",
        "Referrer-Policy" => "strict-origin-when-cross-origin",
        "Content-Security-Policy" => "default-src 'self'; object-src 'none'; frame-ancestors 'none';",
    ]);
});

get("/__ping", fn() => text("pong"));
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
PHP                              1            187             69            720
-------------------------------------------------------------------------------
```

**Full (with Extras):**

```
-------------------------------------------------------------------------------
Language                     files          blank        comment           code
-------------------------------------------------------------------------------
PHP                              1            356             69           1283
-------------------------------------------------------------------------------
```

---

## 🪪 License

Essentio is licensed under the [0BSD License](https://opensource.org/licenses/0BSD). No conditions. No attribution. No nonsense.

Use it. Fork it. Rip it apart. Whatever helps you ship.

---

> Essentio is yours to love, hate, or ignore. The world won’t always agree—but that’s not your problem.
