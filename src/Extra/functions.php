<?php

use Essentio\Core\Extra\Query;

function query(): Query
{
    return app(Query::class);
}
