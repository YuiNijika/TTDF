<?php

declare(strict_types=1);

namespace TTDF\Ajax;

use TTDF\Compat\Typecho;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\Rest\Auth;

final class Guard
{
    /**
     * @param array{handler: callable, method: string, auth: string, nonce: bool, same_origin: bool} $route
     */
    public static function authorize(array $route): void
    {
        $method = Request::method();
        if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
            Response::error('Method not allowed', 405);
        }

        if ($route['auth'] === 'admin' && !Typecho::isAdmin()) {
            Response::error('Unauthorized', 401);
        }

        if ($route['auth'] === 'user' && !Typecho::isLoggedIn()) {
            Response::error('Unauthorized', 401);
        }

        if ($route['nonce'] && !Typecho::checkToken(Request::header(Endpoint::TOKEN_HEADER))) {
            Response::error('Invalid security token', 403);
        }

        if ($route['same_origin'] && !Auth::isSameOrigin(Request::header('Origin'))) {
            Response::error('Invalid origin', 403);
        }
    }
}
