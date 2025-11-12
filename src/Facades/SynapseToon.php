<?php

namespace VinkiusLabs\SynapseToon\Facades;

use Illuminate\Support\Facades\Facade;

class SynapseToon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'synapsetoon.manager';
    }
}
