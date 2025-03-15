<?php

namespace Noma\Core;

#[\Attribute]
class Command
{
    public function __construct(
        public string $command,
    ) {
    }
}