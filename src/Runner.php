<?php

namespace Noma\Core;

interface Runner
{
    public function handleRequest(callable $handler): void;
}