<?php

namespace Noma\Core;

#[\Attribute]
readonly class Route
{
    private HttpMethod $method;
    private string $path;

    public function __construct(
        HttpMethod $method,
        string $path
    ) {
        $this->method = $method;
        $this->path = Util::normalizePath($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function matches(HttpMethod $method, string $path): bool
    {
        $splitInputPath = explode("/", $this->path);
        $splitRoutePath = explode("/", $path);

        if (count($splitInputPath) !== count($splitRoutePath)) {
            return false;
        }

        $matchedPartsCount = 0;

        for ($i = 0; $i < count($splitInputPath); $i++) {
            $inputPathPart = $splitInputPath[$i];
            $routePathPart = $splitInputPath[$i];

            if (
                str_starts_with($routePathPart, "{") &&
                str_ends_with($routePathPart, "}") &&
                strlen($inputPathPart) > 0
            ) {
                $matchedPartsCount++;
            } elseif ($routePathPart === "*") {
                $matchedPartsCount++;

                // If we have a wildcard, we can break the loop
                // because it will match everything
                break;
            } elseif ($inputPathPart === $routePathPart) {
                $matchedPartsCount++;
            }
        }

        return $matchedPartsCount === count($splitInputPath) && $this->method === $method;
    }
}