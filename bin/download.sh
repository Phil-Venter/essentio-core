#!/usr/bin/env bash

set -euo pipefail

mkdir -p app public src/Api src/Cli src/Extra src/Http/Extra src/Web
shared_prefix="https://raw.githubusercontent.com/Phil-Venter/essentio-core/refs/heads/main"

declare -A FILES=(
    ["src/autoload.php"]="${shared_prefix}/src/autoload.php"

    # base
    ["src/Application.php"]="${shared_prefix}/src/Application.php"
    ["src/Container.php"]="${shared_prefix}/src/Container.php"
    ["src/Environment.php"]="${shared_prefix}/src/Environment.php"
    ["src/FrameworkException.php"]="${shared_prefix}/src/FrameworkException.php"
    ["src/functions.php"]="${shared_prefix}/src/functions.php"

    # http
    ["src/Http/HttpException.php"]="${shared_prefix}/src/Http/HttpException.php"
    ["src/Http/ValidationException.php"]="${shared_prefix}/src/Http/ValidationException.php"
    ["src/Http/Request.php"]="${shared_prefix}/src/Http/Request.php"
    ["src/Http/Response.php"]="${shared_prefix}/src/Http/Response.php"
    ["src/Http/Route.php"]="${shared_prefix}/src/Http/Route.php"
    ["src/Http/Router.php"]="${shared_prefix}/src/Http/Router.php"
    ["src/Http/functions.php"]="${shared_prefix}/src/Http/functions.php"

    # api
    ["src/Api/Jwt.php"]="${shared_prefix}/src/Api/Jwt.php"
    ["src/Api/functions.php"]="${shared_prefix}/src/Api/functions.php"

    # web
    ["src/Web/Session.php"]="${shared_prefix}/src/Web/Session.php"
    ["src/Web/Template.php"]="${shared_prefix}/src/Web/Template.php"
    ["src/Web/functions.php"]="${shared_prefix}/src/Web/functions.php"

    # cli
    ["src/Cli/Argument.php"]="${shared_prefix}/src/Cli/Argument.php"
    ["src/Cli/functions.php"]="${shared_prefix}/src/Cli/functions.php"

    # extras
    ["src/Http/Extra/Cast.php"]="${shared_prefix}/src/Http/Extra/Cast.php"
    ["src/Http/Extra/Validate.php"]="${shared_prefix}/src/Http/Extra/Validate.php"
    ["src/Extra/HttpClient.php"]="${shared_prefix}/src/Extra/HttpClient.php"
    ["src/Extra/Query.php"]="${shared_prefix}/src/Extra/Query.php"
    ["src/Extra/functions.php"]="${shared_prefix}/src/Extra/functions.php"

    # stubs
    ["app/PingController.php"]="${shared_prefix}/stubs/app-ping_controller.stub"
    ["routes.php"]="${shared_prefix}/stubs/routes.stub"
    ["public/index.php"]="${shared_prefix}/stubs/public-index.stub"
    ["cli"]="${shared_prefix}/stubs/cli.stub"
    ["bootstrap.php"]="${shared_prefix}/stubs/bootstrap.stub"
)

command -v curl >/dev/null 2>&1 || { echo '❌ curl is required.'; exit 1; }

for path in "${!FILES[@]}"; do
    url="${FILES[$path]}"
    echo "Downloading: $path"
    curl -sSL "$url" -o "$path" &
done
wait

chmod +x cli
