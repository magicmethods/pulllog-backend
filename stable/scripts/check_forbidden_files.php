<?php
declare(strict_types=1);

// Simple repository guard: fail if dot-prefixed PHP sources exist under app/ or routes/

$roots = [__DIR__ . '/../app', __DIR__ . '/../routes'];
$violations = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $base = $file->getBasename();
        if ($base[0] === '.' && str_ends_with($base, '.php')) {
            $path = str_replace('\\', '/', $file->getPathname());
            $sibling = dirname($path) . '/' . substr($base, 1); // remove leading dot
            $note = file_exists($sibling) ? " (duplicate of " . basename($sibling) . ")" : '';
            $violations[] = $path . $note;
        }
    }
}

if (!empty($violations)) {
    fwrite(STDERR, "Forbidden dot-prefixed PHP files detected:\n");
    foreach ($violations as $v) {
        fwrite(STDERR, " - {$v}\n");
    }
    fwrite(STDERR, "\nPlease remove these files. You can add safe files via non-hidden names.\n");
    exit(1);
}

// Optional: also guard against generated/ being committed (already ignored)
exit(0);

