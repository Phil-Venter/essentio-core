# Essentio — Minimalist PHP Framework

Essentio is not designed to impress you with shiny best practices, trendy methodologies, or the approval of coding gurus. This is raw, minimal PHP built strictly for those who want simplicity, speed, and direct control—no more, no less.

## Why Essentio?

Because sometimes you don't want the overhead. You don't need the dogma. You're tired of the "one size fits all" frameworks loaded with unnecessary features. Essentio is intentionally stripped down to just what is essential for bootstrapping small PHP projects, both for CLI and web.

If you see something here that you don't like, that's fine. You have two options:

- **Don't use it.** Seriously, there are plenty of bloated, convention-riddled alternatives out there.
- **Change it yourself.** Essentio is about 600 lines of pure, straightforward PHP (excluding comments). It won't bite. If you want something improved, send a pull request. Pull requests speak louder than bug reports.

## What Essentio Gives You

- Simple and explicit initialization for web or CLI.
- Minimalistic routing without convoluted abstractions.
- Lightweight dependency injection container with zero magic.
- Basic configuration and environment management.
- Simple, understandable session management.
- Clean and straightforward HTTP request and response handling.
- Essential utility functions (dump, env, logging, etc.) without the noise.

## What Essentio Does Not Care About

- Following every single best practice recommended by PHP influencers.
- Catering to complex edge cases or enterprise-level convolutions.
- Pleasing everyone.

## Quickstart

### One file wonder

I have been enamored with the idea of just uploading a single php file to your server and calling it a day.
So that's what I attempted to do, you can run the command below in your project root and start coding at the end of it.

get it: `curl -LO https://raw.githubusercontent.com/Phil-Venter/essentio_core/main/dist/index.php`

NOTE: If something is in `src/` is missing from `dist/index.php` you can compile it anew with `composer run-script build`.

### Composer

You can also install this package via composer: `composer require essentio/core`.

## Global API Reference & Use Cases

Essentio is designed so that nearly every operation you need can be performed via simple global functions. The only exception is the Application class, which you use to initialize and run the application. Use the global functions for tasks like retrieving services, handling HTTP requests, working with sessions, routing, rendering responses, and more.

### `Application::http(string $basePath): void`
- **Purpose:**
  Bootstraps the application for web usage by setting the base path, initializing the dependency container, binding web-specific services (Environment, Request, Router), and starting a session if needed.
- **Example:**
  ```php
  // Initializer for HTTP requests.
  Application::http(__DIR__);
  ```

### `Application::cli(string $basePath): void`
- **Purpose:**
  Sets up the application for CLI commands by binding services for the command-line environment.
- **Example:**
  ```php
  // Initializer for command-line interface.
  Application::cli(__DIR__);
  ```

### `Application::fromBase(string $path): string|false`
- **Purpose:**
  Resolves an absolute file path from a relative path based on the application’s base directory.
- **Example:**
  ```php
  $fullPath = Application::fromBase('config/settings.php');
  ```

### `Application::run(): void`
- **Purpose:**
  In web mode, processes the incoming HTTP request using the registered routes and sends the response. Handles exceptions by returning appropriate HTTP errors.
- **Example:**
  ```php
  // Process the web request after all routes are defined.
  Application::run();
  ```

### Service and Environment Helpers

#### `app(?string $id = null): object`
- **API:**
  Retrieves the dependency container or a specific service if an identifier is provided.
- **Example:**
  ```php
  // Container instance
  $container = app();

  // Router instance
  $router = app(Router::class);
  ```

#### `env(string $key, mixed $default = null): mixed`
- **API:**
  Fetches an environment variable from the loaded configuration.
- **Example:**
  ```php
  // Populate Envireonment with .env values (only need to run once)
  app(Environment::class)->load('.env');

  $dbHost = env('DB_HOST', 'localhost');
  ```

#### `bind(string $id, callable $factory): object`
- **API:**
  Registers a service in the container using a factory callback.
- **Example:**
  ```php
  // to create a new instance everytime you call app(...)
  bind(MyService::class, fn() => new MyService());

  // to create the instance once and return it when you call app(...)
  bind(MyService::class, fn() => new MyService())->once = true;
  ```

### CLI Helpers

#### `arg(int|string $key, mixed $default = null): string|array|null`
- **API:**
  Retrieves a command-line argument by position or name.
- **Example:**
  ```php
  // Get positional argument
  $command = arg(0, 'default');

  // Get named argument
  $command = arg('name', 'default');
  ```

#### `command(string $name, callable $handle): void`
- **API:**
  Executes a CLI command handler if the current command matches the given name.
- **Example:**
  ```php
  command('migrate', function(Argument $args): void {
      // Run migration logic.
  });
  ```

### Request Data Access

#### `request(string $key, mixed $default = null): mixed`
- **API:**
  Gets a value from the HTTP request’s query parameters.
- **Example:**
  ```php
  $user = request('user', 'guest');
  ```

#### `input(string $key, mixed $default = null): mixed`
- **API:**
  Retrieves an input value from the request body (for POST/PUT/PATCH) or query string (for GET-like methods).
- **Example:**
  ```php
  $email = input('email');
  ```

### Routing Helpers

These functions register routes—only active in web mode.

#### `get(string $path, callable $handle, array $middleware = []): void`
- **API:**
  Registers a GET route.
- **Example:**
  ```php
  get('home', function($req, $res) {
      return view('home.php', ['title' => 'Home']);
  });
  ```

#### `post(string $path, callable $handle, array $middleware = []): void`
- **API:**
  Registers a POST route.
- **Example:**
  ```php
  post('submit', function($req, $res) {
      // Handle form submission.
  });
  ```

#### `put(string $path, callable $handle, array $middleware = []): void`
- **API:**
  Registers a PUT route.
- **Example:**
  ```php
  put('update', function($req, $res) {
      // Update resource logic.
  });
  ```

#### `patch(string $path, callable $handle, array $middleware = []): void`
- **API:**
  Registers a PATCH route.
- **Example:**
  ```php
  patch('modify', function($req, $res) {
      // Partial update logic.
  });
  ```

#### `delete(string $path, callable $handle, array $middleware = []): void`
- **API:**
  Registers a DELETE route.
- **Example:**
  ```php
  delete('remove', function($req, $res) {
      // Delete resource logic.
  });
  ```

Middleware is just a glorified `Closure`
```php
function (Request $request, Response $response, callable $next): Response {
    if ($request->scheme === 'http') {
        return $response->withStatus(400)->addHeaders(['X-INSECURE' => true])->withBody('');
    }

    $next($request, $response);
}
```

### Session and Flash Data

#### `flash(string $key, mixed $value = null): mixed`
- **API:**
  If a value is provided, sets flash data; if omitted, retrieves and then removes the flash data.
- **Example:**
  ```php
  // Set a flash message.
  flash('notice', 'Operation successful!');

  // Retrieve and clear the flash message.
  $notice = flash('notice');
  ```

#### `session(string $key, mixed $value = null): mixed`
- **API:**
  Gets or sets session data.
- **Example:**
  ```php
  session('user_id', 42);
  $userId = session('user_id');
  ```

### Templating and Response Creation

#### `render(string $template, array $data = []): string`
- **API:**
  Renders a template file (if found) or processes an inline template by replacing placeholders with data.
- **Example:**
  ```php
  $html = render('views/dashboard.php', ['user' => $currentUser]);
  ```

#### `redirect(string $uri, int $status = 302): Response`
- **API:**
  Generates a redirect response to the specified URI.
- **Example:**
  ```php
  return redirect('/login');
  ```

#### `json(mixed $data, int $status = 200): Response`
- **API:**
  Creates a JSON response with the given data and status code.
- **Example:**
  ```php
  return json(['success' => true]);
  ```

#### `text(string $text, int $status = 200): Response`
- **API:**
  Creates a plain text response.
- **Example:**
  ```php
  return text('Hello World');
  ```

#### `view(string $template, array $data = [], int $status = 200): Response`
- **API:**
  Renders an HTML view and returns it as a response.
- **Example:**
  ```php
  return view('profile.php', ['user' => $currentUser]);
  ```

### Logging and Debugging

#### `log_cli(string $format, ...$values): void`
- **API:**
  Logs a formatted message (suitable for CLI scripts) using PHP’s error_log.
- **Example:**
  ```php
  log_cli("Migration started at %s", date('H:i:s'));
  ```

#### `logger(string $level, string $message): void`
- **API:**
  Logs a message at a specified level to a file determined by the configuration.
- **Example:**
  ```php
  logger('error', 'An error occurred while processing the request.');
  ```

#### `dump(...$data): void`
- **API:**
  Outputs debug information; in web mode, it wraps the output in `<pre>` tags.
- **Example:**
  ```php
  dump($user, $debugData);
  ```

### Utility Functions

#### `pipeline(callable ...$callbacks): callable`
- **API:**
  Combines multiple callbacks into a single callable that processes an argument through each callback sequentially.
- **Example:**
  ```php
  $process = pipeline(fn($x) => $x + 2, fn($x) => $x * 3);
  echo $process(4); // Outputs: 18
  ```

#### `retry(int $times, callable $callback, int $sleep = 0): mixed`
- **API:**
  Attempts to execute a callback multiple times (with an optional delay) before failing.
- **Example:**
  ```php
  $result = retry(3, function() {
      // Risky operation.
  }, 100);
  ```

#### `safe(callable $callback, mixed $default = null): mixed`
- **API:**
  Executes a callback; if it fails, logs the error and returns the default value.
- **Example:**
  ```php
  $result = safe(fn() => performRiskyTask(), 'fallback');
  ```

#### `tap(mixed $value, callable $callback): mixed`
- **API:**
  Runs a callback with a given value for side effects and then returns the original value.
- **Example:**
  ```php
  $user = tap($user, fn($u) => logger('info', "Processed user ID: {$u->id}"));
  ```

#### `throw_if(bool $condition, \Throwable $e): void`
- **API:**
  Throws the specified exception if the condition is true.
- **Example:**
  ```php
  throw_if(empty($data), new Exception("Data cannot be empty"));
  ```

#### `value(mixed $value): mixed`
- **API:**
  If the provided value is callable, executes it and returns its result; otherwise, returns the value.
- **Example:**
  ```php
  $result = value(fn() => 42); // Returns 42.
  ```

#### `when(bool $condition, mixed $value): mixed`
- **API:**
  If the condition is true, returns the evaluated value (if callable) or the value itself; otherwise, returns null.
- **Example:**
  ```php
  $result = when(true, fn() => "Condition met"); // Returns "Condition met".
  ```

### TL;DR

- **Initialization and Execution:**
  Use `Application::http()` or `Application::cli()` to start your app, and `Application::run()` to process web requests.

- **Everyday Tasks:**
  Rely on the global functions for routing (`get()`, `post()`, etc.), service management (`app()`, `env()`, `bind()`), handling request data (`request()`, `input()`), generating responses (`redirect()`, `json()`, `text()`, `view()`), and performing utility operations.

## Customization & Extending

It's deliberately small—extend it yourself. Add your middleware, improve error handling, or replace components entirely. Fork it, mold it to your project, or just tweak what irritates you.

Essentio is a base, not a cage.

## License

MIT License. Freedom to use, freedom to change, freedom to ignore.

---

Essentio is yours to love, hate, or improve. The world won't always agree—but that's not your problem.
