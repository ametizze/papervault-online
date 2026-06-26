<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use SimpleVault\Core\Response;
use SimpleVault\Core\Session;
use SimpleVault\Core\View;

/**
 * Base controller with shared response/redirect/flash helpers.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], string $title = ''): Response
    {
        return View::render($view, $data, $title);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(string $fallback = '/'): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        // Only honor same-origin relative referers to avoid open redirects.
        if (is_string($referer)) {
            $path = parse_url($referer, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/')) {
                return $this->redirect($path);
            }
        }

        return $this->redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function userId(): int
    {
        return (int) Session::userId();
    }
}
