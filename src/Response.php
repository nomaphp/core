<?php

namespace Noma\Core;

class Response
{
    private string $body;

    public function __construct(string $body)
    {
        $this->body = $body;
    }

    public static function text(string $text): static
    {
        return new static(
            body: $text . PHP_EOL,
        );
    }

    public function deliver(): void
    {
        echo $this->body;
    }
}