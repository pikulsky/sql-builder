<?php

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new \RuntimeException('Missing composer autoload: ' . $autoload);
}

// return

return require_once $autoload;
