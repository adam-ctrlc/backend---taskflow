<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;

class PostgresBooleanServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Override the boolean method for PostgreSQL
        Blueprint::macro('boolean', function ($column) {
            return $this->addColumn('boolean', $column);
        });
    }
}