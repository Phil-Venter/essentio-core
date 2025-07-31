#!/usr/bin/env bash

set -euo pipefail

mkdir -p app
mkdir -p public
mkdir -p src/Api
mkdir -p src/Cli
mkdir -p src/Extra
mkdir -p src/Http/Extra
mkdir -p src/Web

declare -A FILES=(
    # base
    ["src/Application.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Application.php"
    ["src/Container.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Container.php"
    ["src/Environment.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Environment.php"
    ["src/FrameworkException.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/FrameworkException.php"
    ["src/functions.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/functions.php"

    # http
    ["src/Http/HttpException.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/HttpException.php"
    ["src/Http/ValidationException.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/ValidationException.php"
    ["src/Http/Request.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/Request.php"
    ["src/Http/Response.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/Response.php"
    ["src/Http/Route.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/Route.php"
    ["src/Http/Router.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/Router.php"
    ["src/Http/functions.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/functions.php"

    # api
    ["src/Api/Jwt.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Api/Jwt.php"
    ["src/Api/functions.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Api/functions.php"

    # web
    ["src/Web/Session.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Web/Session.php"
    ["src/Web/Template.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Web/Template.php"
    ["src/Web/functions.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Web/functions.php"

    # cli
    ["src/Cli/Argument.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Cli/Argument.php"
    ["src/Cli/functions.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Cli/functions.php"

    # extras
    ["src/Http/Extra/Cast.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/Extra/Cast.php"
    ["src/Http/Extra/Validate.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Http/Extra/Validate.php"
    ["src/Extra/HttpClient.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Extra/HttpClient.php"
    ["src/Extra/Query.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Extra/Query.php"
    ["src/Extra/functions.php"]="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main/src/Extra/functions.php"
)

command -v curl >/dev/null 2>&1 || { echo '❌ curl is required.'; exit 1; }

for path in "${!FILES[@]}"; do
    url="${FILES[$path]}"
    echo "Downloading: $path"
    curl -sSL "$url" -o "$path" &
done
wait

echo "Creating: bootstrap.php"
cat << 'EOF' > bootstrap.php
<?php

use Essentio\Extra\Query;

once(PDO::class, fn() => new PDO("sqlite:" . base_path("database.sqlite")));
bind(Query::class, fn() => new Query(app(PDO::class)));
EOF

echo "Creating: public/index.php"
cat << 'EOF' > public/index.php
<?php

// autoload web
require_once __DIR__ . '/../src/Application.php';

Essentio\Application::autoload([
    'App'      => __DIR__ . '/../app',
    'Essentio' => __DIR__ . '/../src',
]);

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/Api/functions.php';
require_once __DIR__ . '/../src/Extra/functions.php';
require_once __DIR__ . '/../src/Http/functions.php';
require_once __DIR__ . '/../src/Web/functions.php';

// init and run web
Essentio\Application::http(__DIR__ . '/..');
require_once base_path('/bootstrap.php');
require_once base_path('/routes.php');
Essentio\Application::run();
EOF

echo "Creating: cli"
cat << 'EOF' > cli
#!/usr/bin/env php
<?php

// autoload cli
require_once __DIR__ . '/src/Application.php';

Essentio\Application::autoload([
    'Essentio' => __DIR__ . '/src'
]);

require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/Cli/functions.php';
require_once __DIR__ . '/src/Extra/functions.php';

// init and run cli
Essentio\Application::cli(__DIR__);
require_once base_path('/bootstrap.php');

command('serve', fn() => exec('php -S localhost:8000 -t public'));

Essentio\Application::run();
EOF

echo "Creating: routes.php"
cat << 'EOF' > routes.php
<?php

get('__ping', [App\PingController::class, 'ping']);
EOF

echo "Creating: app/PingController.php"
cat << 'EOF' > app/PingController.php
<?php

namespace App;

use Essentio\Http\Response;

class PingController
{
    public static function ping(): Response
    {
        return text('pong');
    }
}
EOF
