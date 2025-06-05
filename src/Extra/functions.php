<?php

use Essentio\Core\Extra\Cast;
use Essentio\Core\Extra\Mailer;
use Essentio\Core\Extra\Query;
use Essentio\Core\Extra\Validate;

function cast(): Cast
{
    return app(Cast::class);
}

function mailer(?string $url = null, ?string $user = null, ?string $pass = null, ?int $port = null): Mailer
{
    $url ??= env("MAILER_URL");
    $user ??= env("MAILER_USER");
    $pass ??= env("MAILER_PASS");
    $port ??= env("MAILER_PORT", 587);

    return map(Mailer::class, compact("url", "user", "pass", "port"));
}

function query(?PDO $pdo): Query
{
    return map(Query::class, [$pdo ?? app(PDO::class)]);
}

function validate(): Validate
{
    return app(Validate::class);
}
