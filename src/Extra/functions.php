<?php

use Essentio\Core\Extra\Cast;
use Essentio\Core\Extra\Mailer;
use Essentio\Core\Extra\Query;
use Essentio\Core\Extra\Validate;

function cast(): Cast
{
    return app(Cast::class);
}

function mailer(): Mailer
{
    return app(Mailer::class);
}

function query(): Query
{
    return new Query(app(PDO::class));
}

function validate(): Validate
{
    return new Validate();
}
