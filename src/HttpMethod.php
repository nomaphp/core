<?php

namespace Noma\Core;

enum HttpMethod
{
    case GET;
    case HEAD;
    case POST;
    case PUT;
    case DELETE;
    case CONNECT;
    case OPTIONS;
    case TRACE;
    case PATCH;

    public static function from(string $method): HttpMethod
    {
        return match ($method) {
            'GET' => HttpMethod::GET,
            'HEAD' => HttpMethod::HEAD,
            'POST' => HttpMethod::POST,
            'PUT' => HttpMethod::PUT,
            'DELETE' => HttpMethod::DELETE,
            'CONNECT' => HttpMethod::CONNECT,
            'OPTIONS' => HttpMethod::OPTIONS,
            'TRACE' => HttpMethod::TRACE,
            'PATCH' => HttpMethod::PATCH,
        };
    }
}