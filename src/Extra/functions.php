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
function mailer(): Mailer
{
    return app(Mailer::class);
}

/**
 * Returns a new query builder instance using the configured PDO.
 *
 * @return Query
 */
function query(): Query
{
    return new Query(app(PDO::class));
}

/**
 * Returns a new instance of the validation rules provider.
 *
 * @return Validate
 */
function validate(): Validate
{
    return new Validate();
}
