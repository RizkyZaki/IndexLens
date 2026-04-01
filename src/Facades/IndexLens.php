<?php

namespace IndexLens\IndexLens\Facades;

use Illuminate\Support\Facades\Facade;

class IndexLens extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'indexlens';
    }
}
