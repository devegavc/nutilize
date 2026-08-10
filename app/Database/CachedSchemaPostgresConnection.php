<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection;

class CachedSchemaPostgresConnection extends PostgresConnection
{
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new CachedSchemaPostgresBuilder($this);
    }
}
