<?php

namespace App\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, int $status = 200): void
    {
        self::json(['success' => true, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        $payload = ['success' => false, 'error' => $message];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $status);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function view(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            echo "View not found: {$view}";
            exit;
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // Daca view-ul nu este layout-ul insusi, il incadram in layout/app.php
        if (!str_starts_with($view, 'layout')) {
            $pageContent = $content;
            require __DIR__ . '/../Views/layout/app.php';
        } else {
            echo $content;
        }
    }
}
