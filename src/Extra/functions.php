<?php

use Essentio\Core\Extra\Cast;
use Essentio\Core\Extra\Mailer;
use Essentio\Core\Extra\Query;
use Essentio\Core\Extra\Validate;

/**
 * Returns a new instance of the Cast utility class.
 *
 * @return Cast
 */
function cast(): Cast
{
    return app(Cast::class);
}

/**
 * Returns a new instance of the Mailer service.
 *
 * @return Mailer
 */
function mailer(?string $url = null, ?string $user = null, ?string $pass = null, ?int $port = null): Mailer
{
    $url ??= env("MAILER_URL");
    $user ??= env("MAILER_USER");
    $pass ??= env("MAILER_PASS");
    $port ??= env("MAILER_PORT", 587);

    return app(Mailer::class, compact("url", "user", "pass", "port"));
}

/**
 * Returns a new query builder instance using the configured PDO.
 *
 * @return Query
 */
function query(?PDO $pdo): Query
{
    return app(Query::class, [$pdo ?? app(PDO::class)]);
}

/**
 * Returns a new instance of the validation rules provider.
 *
 * @return Validate
 */
function validate(): Validate
{
    return app(Validate::class);
}
