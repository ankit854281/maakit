<?php
// CI: test fail ho to asli wajah GitHub annotation me dikhe (logs na khul payein tab bhi)
set_exception_handler(function ($e) {
    echo '::error::' . basename($_SERVER['SCRIPT_FILENAME'] ?? 'test') . ' ' . get_class($e) . ': ' . str_replace(["\n", "\r"], ' ', $e->getMessage()) . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    exit(1);
});
