<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 使用 Enum 定义常量
enum HttpCode: int
{
    case OK = 200;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case INTERNAL_ERROR = 500;
}

enum ContentFormat: string
{
    case HTML = 'html';
    case MARKDOWN = 'markdown';
}
