<?php

namespace SchoolAid\Zuma\Facades;

use Illuminate\Support\Facades\Facade;

class Zuma extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zuma';
    }
}