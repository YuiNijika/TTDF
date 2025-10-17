<?php

declare(strict_types=1);

/**
 * TTDF REST API
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// -----------------------------------------------------------------------------
// 配置与常量定义
// -----------------------------------------------------------------------------
// 检查REST API是否启用
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');
$pathParts = explode('/', trim(str_replace($basePath, '', $requestUri), '/'));
$endpoint = $pathParts[0] ?? '';

// 检查REST API是否启用
if ($endpoint !== 'ttdf') {
    $restApiEnabled = true; // 默认值

    // 检查主题设置项
    $restApiSwitch = Get::Options(TTDF_CONFIG['REST_API']['OVERRIDE_SETTING'] ?? 'TTDF_RESTAPI_Switch');
    if ($restApiSwitch === 'false') {
        $restApiEnabled = false;
    }
    // 如果没有设置项，则使用常量配置
    elseif (!isset($restApiSwitch)) {
        $restApiEnabled = TTDF_CONFIG['REST_API']['ENABLED'] ?? false;
    }

    // 最终检查
    if (!$restApiEnabled) {
        if (!isset(Typecho\Router::$current)) {
            Typecho\Router::$current = '';
        }
        return;
    }
}

// 引入REST API模块
require_once __DIR__ . '/Rest/Enums.php';
require_once __DIR__ . '/Rest/Middleware.php';
require_once __DIR__ . '/Rest/Core.php';
require_once __DIR__ . '/Rest/Router.php';

// -----------------------------------------------------------------------------
// 应用启动入口 (Entry Point)
// -----------------------------------------------------------------------------
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$basePath = '/' . ltrim(__TTDF_RESTAPI_ROUTE__ ?? '', '/');

if (str_starts_with($requestUri, $basePath)) {
    // 保存原始错误处理设置
    $originalDisplayErrors = null;
    $originalErrorHandlerDisplay = null;

    // 如果 DEBUG 为 false，临时禁用错误显示
    if (!(TTDF_CONFIG['DEBUG'] ?? false)) {
        $originalDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');

        // 临时禁用 TTDF 错误处理器的页面显示
        if (class_exists('TTDF_ErrorHandler')) {
            $errorHandler = TTDF_ErrorHandler::getInstance();
            $originalErrorHandlerDisplay = $errorHandler->getDisplayErrors();
            $errorHandler->setDisplayErrors(false);
        }
    }

    try {
        // 记录API请求开始
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('START', [
                'request_uri' => $requestUri,
                'base_path' => $basePath,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'
            ]);
        }

        // 验证Token
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('TOKEN_VALIDATION');
        }
        TokenValidator::validate();
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('TOKEN_VALIDATION_SUCCESS');
        }

        // 初始化组件
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('INITIALIZING_COMPONENTS');
        }
        $request   = new ApiRequest();
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('API_REQUEST_CREATED', [
                'path' => $request->path,
                'path_parts' => $request->pathParts
            ]);
        }

        $response  = new ApiResponse($request->contentFormat);
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('API_RESPONSE_CREATED');
        }

        $db        = new TTDF_Db_API();
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('TTDF_Db_API_CREATED');
        }

        $formatter = new ApiFormatter($db, $request->contentFormat, $request->excerptLength);
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('API_FORMATTER_CREATED');
        }

        $api = new TTDF_API($request, $response, $db, $formatter);
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('TTDF_API_CREATED');
        }

        // 处理请求
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiProcess('HANDLING_REQUEST');
        }
        $api->handleRequest();
    } catch (Throwable $e) {
        if ((TTDF_CONFIG['DEBUG'] ?? false) && class_exists('TTDF_Debug')) {
            TTDF_Debug::logApiError('API Bootstrap Error', $e);
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        error_log("API Bootstrap Error: " . $e->getMessage());
        echo json_encode([
            'code' => 500,
            'message' => 'API failed to start.',
            'error' => defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__ ? $e->getMessage() : 'An unexpected error occurred.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } finally {
        // 恢复原始错误处理设置
        if (!(TTDF_CONFIG['DEBUG'] ?? false)) {
            if ($originalDisplayErrors !== null) {
                ini_set('display_errors', $originalDisplayErrors);
            }

            if ($originalErrorHandlerDisplay !== null && class_exists('TTDF_ErrorHandler')) {
                $errorHandler = TTDF_ErrorHandler::getInstance();
                $errorHandler->setDisplayErrors($originalErrorHandlerDisplay);
            }
        }
    }
}
