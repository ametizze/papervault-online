<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * Very small PHP template renderer. Views are plain PHP files under app/Views.
 * The layout wraps a rendered child view via the `$content` variable.
 */
final class View
{
    /**
     * Render a view file with data, wrapped in the main layout.
     */
    public static function render(string $view, array $data = [], string $title = ''): Response
    {
        $content = self::renderPartial($view, $data);

        $layoutData = $data + [
            'content' => $content,
            'title' => $title !== '' ? $title : (string) App::config('app_name', 'SimpleVault'),
            'flash' => Session::takeFlash(),
        ];

        $html = self::renderPartial('layout', $layoutData);

        return Response::html($html);
    }

    /**
     * Render a view file without the layout.
     */
    public static function renderPartial(string $view, array $data = []): string
    {
        $path = base_path('app/Views/' . $view . '.php');
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }
}
