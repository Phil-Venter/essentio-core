<?php

use Essentio\Core\Extra\Query;

/**
 * Get a Query builder instance.
 *
 * @return Query
 */
function query(): Query
{
    return app(Query::class);
}
