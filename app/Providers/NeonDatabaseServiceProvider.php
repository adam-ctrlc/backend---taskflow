<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connectors\PostgresConnector;
use PDO;

class NeonDatabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('db.connector.pgsql', function ($app) {
            return new class extends PostgresConnector {
                protected function getDsn(array $config)
                {
                    $dsn = "pgsql:host={$config['host']};dbname='{$config['database']}';port={$config['port']};";
                    
                    if (isset($config['charset'])) {
                        $dsn .= "client_encoding='{$config['charset']}';";
                    }
                    
                    if (isset($config['sslmode'])) {
                        $dsn .= "sslmode={$config['sslmode']};";
                    }
                    
                    // Add Neon endpoint support
                    if (env('DB_ENDPOINT')) {
                        $dsn .= "options='endpoint=" . env('DB_ENDPOINT') . "';";
                    }
                    
                    return $dsn;
                }
            };
        });
    }
}