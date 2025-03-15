<?php

namespace Noma\Core;

class Util
{
    public static function normalizePath(string $path): string
    {
        $normalizedPath = explode('?', trim($path, "/"))[0];

        if (empty($normalizedPath)) {
            return "/";
        }

        return "/{$normalizedPath}";
    }
}