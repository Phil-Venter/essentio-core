<?php

use Essentio\Core\Extra\{Authenticated, Cast, Mailer, Query, Validate};

function auth(): Authenticated
{
    return app(Authenticated::class);
}

function user(): ?object
{
    return auth()->user();
}

function cast(): Cast
{
    return app(Cast::class);
}

function mailer(?string $url = null, ?string $user = null, ?string $pass = null, ?int $port = null): Mailer
{
    return map(Mailer::class, compact("url", "user", "pass", "port"));
}

function query(?PDO $pdo = null): Query
{
    return map(Query::class, compact("pdo"));
}

function validate(): Validate
{
    return app(Validate::class);
}
