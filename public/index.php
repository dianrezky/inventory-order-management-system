<?php

namespace App\Core;

use App\Controller\BaseController;

if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($requestedFile !== __DIR__ . '/index.php' && is_file($requestedFile)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);

// ERR-01.04/.05: PHP warnings, notices and fatals are logged, never rendered —
// a displayed "Warning: … in /var/www/html/…" leaks file paths and internals
// (the php:*-cli image ships no php.ini, so display_errors defaulted to on).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Gzip HTML/JSON for clients that accept it (every browser). Besides the
// smaller payload, this keeps pages well under 64KB on the wire: larger
// uncompressed responses from `php -S` were intermittently truncated and
// stalled ~20s behind Docker Desktop's port forwarding (seen on /products).
if (extension_loaded('zlib')) {
    ini_set('zlib.output_compression', '1');
}

if (is_file($rootPath . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
}

// Load constants first (REGEX_PATTERN, REGEX_NUMBER, ROUTE_TYPE_*)
require $rootPath . '/config/global.php';

$config = require $rootPath . '/config/global.php';
$config['views.path'] = $rootPath . '/views';

$container = new Container($config);

$session = $container->getSessionManager();
$session->start();

// Get HTTP request details
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// Home page redirect
if ($method === 'GET' && $path === '/') {
    header('Location: /dashboard', true, 302);
    exit;
}

// Load route configuration
$routes = require $rootPath . '/config/routes.php';

$matchRouteAgainstCandidates = function (array $childRoutes, string $method, string $path): ?array {
    foreach ($childRoutes as $routeName => $routeConfig) {
        $options = $routeConfig['options'] ?? [];

        // Check HTTP method
        $allowedMethods = $options['method'] ?? ['GET'];
        if (!in_array($method, $allowedMethods, true)) {
            continue;
        }

        $routePattern = $options['route'] ?? '';
        $defaults = $options['defaults'] ?? [];
        $constraints = $options['constraints'] ?? [];

        $match = matchPathAgainstPattern($routePattern, $path, $constraints);
        if ($match !== null) {
            $controller = $defaults['controller'] ?? null;
            $action = $defaults['action'] ?? null;

            if ($controller === null || $action === null) {
                continue;
            }

            return [
                'controller' => $controller,
                'action' => $action,
                'params' => $match['params'],
                'types' => $match['types'],
            ];
        }
    }

    return null;
};

$matchRoute = function (array $routes, string $method, string $path) use ($matchRouteAgainstCandidates): ?array {
    foreach ($routes as $routeName => $routeConfig) {
        $options = $routeConfig['options'] ?? [];
        $childRoutes = $routeConfig['child_routes'] ?? [];

        // Child routes carry their own method list, independent of the parent's, so
        // they must be tried before the parent's own method gate below rules them out
        // (e.g. a GET-only parent with a POST-only 'store' child).
        if (!empty($childRoutes)) {
            $childMatch = $matchRouteAgainstCandidates($childRoutes, $method, $path);
            if ($childMatch !== null) {
                return $childMatch;
            }
        }

        // Check HTTP method
        $allowedMethods = $options['method'] ?? ['GET'];
        if (!in_array($method, $allowedMethods, true)) {
            continue;
        }

        // Match against main route
        $routePattern = $options['route'] ?? '';
        $defaults = $options['defaults'] ?? [];
        $constraints = $options['constraints'] ?? [];

        $match = matchPathAgainstPattern($routePattern, $path, $constraints);
        if ($match !== null) {
            $controller = $defaults['controller'] ?? null;
            $action = $defaults['action'] ?? null;

            if ($controller === null || $action === null) {
                continue;
            }

            return [
                'controller' => $controller,
                'action' => $action,
                'params' => $match['params'],
                'types' => $match['types'],
            ];
        }
    }

    return null;
};

function matchPathAgainstPattern(string $pattern, string $path, array $constraints): ?array
{
    // Convert pattern to regex: {param} -> named capture group

    $paramNames = [];
    $regexPattern = $pattern;

    // Replace {param} with named capture groups
    $regexPattern = preg_replace_callback(
        '/\{(\w+)\}/',
        function ($matches) use ($constraints, &$paramNames) {
            $paramName = $matches[1];
            $paramNames[] = $paramName;

            if (isset($constraints[$paramName])) {
                return '(?P<' . $paramName . '>' . $constraints[$paramName] . ')';
            }

            // Default: match anything except slash
            return '(?P<' . $paramName . '>[^/]+)';
        },
        $regexPattern
    );

    // Escape remaining characters for regex
    $regexPattern = '#^' . $regexPattern . '$#u';

    $matched = preg_match($regexPattern, $path, $matches);

    if (!$matched) {
        return null;
    }

    $params = [];
    $types = [];

    foreach ($paramNames as $name) {
        if (isset($matches[$name]) && $matches[$name] !== '') {
            $params[] = $matches[$name];

            // Determine type based on constraint or default
            if (isset($constraints[$name])) {
                $constraint = $constraints[$name];
                if ($constraint === '\d+' || $constraint === '[0-9]+') {
                    $types[] = 'int';
                } else {
                    $types[] = 'string';
                }
            } else {
                // Try to infer type
                if (is_numeric($matches[$name])) {
                    $types[] = 'int';
                } else {
                    $types[] = 'string';
                }
            }
        }
    }

    return [
        'params' => $params,
        'types' => $types,
    ];
}

function renderViewModel(ViewModel $viewModel, Container $container): void
{
    $viewsPath = (string) $container->config('views.path');
    $template = $viewModel->getTemplate();
    $data = $viewModel->getData();
    $layout = $viewModel->getLayout();

    if ($viewModel->getStatusCode() !== 200) {
        http_response_code($viewModel->getStatusCode());
    }

    // Render the view
    (function () use ($viewsPath, $template, $data, $layout) {
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewsPath . '/' . $template . '.php';
        $content = (string) ob_get_clean();

        // If terminal (no layout), output content directly
        if ($layout === null) {
            echo $content;
            return;
        }

        // Extract data again for layout
        extract($data, EXTR_SKIP);
        require $viewsPath . '/' . $layout . '.php';
    })();
}

// Renders Response::notFound()/forbidden()/badRequest() as a proper styled,
// terminal (layout-less) page instead of the bare message text those Response
// factories carry — a raw unstyled string replacing the whole page, with no
// navigation back into the app, is exactly the dead-end this function exists
// to avoid (see ProductController::processImageUpload()'s history: the same
// symptom, from a different cause).
function renderErrorPage(int $statusCode, string $message, Container $container): void
{
    $viewsPath = (string) $container->config('views.path');
    $data = [
        'statusCode' => $statusCode,
        'message' => $message,
        'isLoggedIn' => $container->getAuthService()->currentUser() !== null,
    ];

    (function () use ($viewsPath, $data) {
        extract($data, EXTR_SKIP);
        require $viewsPath . '/errors/error.php';
    })();
}

function handleResponse($response, Container $container): void
{
    // ViewModel response - render view
    if ($response instanceof ViewModel) {
        renderViewModel($response, $container);
        return;
    }

    // Response object - handle based on type
    if ($response instanceof Response) {
        http_response_code($response->getStatusCode());

        switch ($response->getType()) {
            case Response::TYPE_REDIRECT:
                header('Location: ' . $response->getData(), true, $response->getStatusCode());
                exit;

            case Response::TYPE_JSON:
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($response->getData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                exit;

            case Response::TYPE_CSV:
                $csvData = $response->getData();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $csvData['filename'] . '"');
                echo $csvData['content'];
                exit;

            case Response::TYPE_NOT_FOUND:
            case Response::TYPE_FORBIDDEN:
            case Response::TYPE_BAD_REQUEST:
                renderErrorPage($response->getStatusCode(), (string) $response->getData(), $container);
                exit;

            default:
                echo $response->getData();
                exit;
        }
    }

    // null response - nothing to render
    if ($response === null) {
        return;
    }
}

// Perform route matching
$match = $matchRoute($routes, $method, $path);

if ($match === null) {
    http_response_code(404);
    if (str_starts_with($path, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    renderErrorPage(404, 'The page you requested does not exist.', $container);
    exit;
}

$controllerClass = $match['controller'];
$action = $match['action'];
$params = $match['params'];
$types = $match['types'];

// Convert params to proper types
$args = [];
foreach ($params as $i => $param) {
    $type = $types[$i] ?? 'string';
    $args[] = $type === 'int' ? (int) $param : $param;
}

try {
    $controller = new $controllerClass($container);
    $response = $controller->$action(...$args);

    // Handle the response from controller
    handleResponse($response, $container);
} catch (\Throwable $e) {
    // Last-resort catch - catches any exception that slipped through
    // controller-level catches (new type added to service but forgot to
    // update controller, DB connection failure, bug, etc.)
    // The full trace goes to the log only. (A ?__debug query flag used to echo
    // it to anyone, in any environment — B-73 / ERR-01.05.)
    error_log($e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    // API-01: /api/* callers always get JSON, including on failure.
    if (str_starts_with($path, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'internal_error']);
        exit;
    }
    try {
        renderErrorPage(500, 'An internal error occurred. Please try again later.', $container);
    } catch (\Throwable $renderError) {
        // Last-resort fallback if even the styled error page can't render
        // (e.g. the failure happened before $container was usable) — still
        // better to fall back to plain text than to let this second
        // exception surface as an uncaught fatal.
        echo 'An internal error occurred. Please try again later.';
    }
    exit;
}
