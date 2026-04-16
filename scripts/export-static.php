<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templatePath = $root . '/resources/views/welcome.blade.php';
$manifestPath = $root . '/public/build/manifest.json';
$distPath = $root . '/dist';

if (! file_exists($templatePath)) {
    fwrite(STDERR, "Template not found: {$templatePath}\n");
    exit(1);
}

if (! file_exists($manifestPath)) {
    fwrite(STDERR, "Build manifest not found. Run `npm run build` first.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (! is_array($manifest)) {
    fwrite(STDERR, "Invalid manifest file.\n");
    exit(1);
}

$cssFile = $manifest['resources/css/app.css']['file'] ?? null;
$jsFile = $manifest['resources/js/app.js']['file'] ?? null;

if (! is_string($cssFile) || ! is_string($jsFile)) {
    fwrite(STDERR, "CSS or JS entry not found in manifest.\n");
    exit(1);
}

$basePath = getenv('EXPORT_BASE_PATH') ?: '/';
$basePath = '/' . trim($basePath, '/') . '/';
$basePath = $basePath === '//' ? '/' : $basePath;

$template = (string) file_get_contents($templatePath);
$viteTags = implode("\n", [
    '        <link rel="stylesheet" href="' . $basePath . 'build/' . $cssFile . '">',
    '        <script type="module" src="' . $basePath . 'build/' . $jsFile . '"></script>',
]);

$html = str_replace(
    [
        "{{ str_replace('_', '-', app()->getLocale()) }}",
        "@vite(['resources/css/app.css', 'resources/js/app.js'])",
        "@vite('resources/css/app.css')",
        '@vite("resources/css/app.css")',
    ],
    [
        'en',
        $viteTags,
        $viteTags,
        $viteTags,
    ],
    $template
);

$html = preg_replace_callback(
    '/\{\{\s*asset\((["\'])(.+?)\1\)\s*\}\}/',
    static function (array $matches) use ($basePath): string {
        $path = ltrim($matches[2], '/');

        return $basePath . $path;
    },
    $html
);

if (str_contains($html, '@vite(') || str_contains($html, '{{')) {
    fwrite(STDERR, "Static export still contains Blade syntax. Check the template.\n");
    exit(1);
}

deleteDirectory($distPath);
mkdir($distPath, 0777, true);

file_put_contents($distPath . '/index.html', $html);
copyPublicDirectory($root . '/public', $distPath);

file_put_contents($distPath . '/.nojekyll', '');

fwrite(STDOUT, "Static export created in {$distPath}\n");
fwrite(STDOUT, "Base path: {$basePath}\n");

function copyDirectory(string $source, string $destination): void
{
    if (! is_dir($source)) {
        return;
    }

    if (! is_dir($destination)) {
        mkdir($destination, 0777, true);
    }

    $items = scandir($source);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $sourcePath = $source . '/' . $item;
        $destinationPath = $destination . '/' . $item;

        if (is_dir($sourcePath)) {
            copyDirectory($sourcePath, $destinationPath);
            continue;
        }

        copy($sourcePath, $destinationPath);
    }
}

function copyPublicDirectory(string $source, string $destination): void
{
    if (! is_dir($source)) {
        return;
    }

    $items = scandir($source);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if ($item === 'index.php' || $item === 'hot') {
            continue;
        }

        $sourcePath = $source . '/' . $item;
        $destinationPath = $destination . '/' . $item;

        if (is_dir($sourcePath)) {
            copyDirectory($sourcePath, $destinationPath);
            continue;
        }

        copy($sourcePath, $destinationPath);
    }
}

function deleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = scandir($directory);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;

        if (is_dir($path)) {
            deleteDirectory($path);
            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}
