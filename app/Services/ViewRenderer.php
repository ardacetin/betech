<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class ViewRenderer
{
    public function __construct(
        private readonly string $viewsPath
    ) {
    }

    /**
     * Render a view file, optionally wrapped in a layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], ?string $layout = 'layout'): string
    {
        $content = $this->renderPartial($view, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderPartial($layout, array_merge($data, [
            'content' => $content,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderPartial(string $view, array $data): string
    {
        $viewFile = $this->viewsPath . '/' . $view . '.php';

        if (!is_readable($viewFile)) {
            throw new RuntimeException(sprintf('View file not found: %s', $viewFile));
        }

        extract($data, EXTR_SKIP);

        ob_start();

        require $viewFile;

        return ob_get_clean() ?: '';
    }
}
