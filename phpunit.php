<?php
error_reporting(E_ALL);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new \RuntimeException('Missing composer autoload: ' . $autoload);
}
/** @var \Composer\Autoload\ClassLoader $loader */
$loader =  require $autoload;

// set up tests autoloading
$files = rglob(__DIR__ . '/tests', '/*.php');
$psr4 = array();
$added = array();

foreach ($files as $filename) {
    $ns = ns($filename);
    if (!array_key_exists($ns, $psr4)) {
        $psr4[$ns] = array();
    }
    $dir = dirname($filename);
    if (false !== array_search($dir, $added)) {
        continue;
    }
    $psr4[$ns][] = $added[] = $dir;
}
foreach ($psr4 as $ns => $paths) {
    $loader->addPsr4($ns, $paths);
}

function rglob($path, $pattern = '*', $flags = 0) {
    //TODO: refactor so pattern only is used including case with {}
    $paths = glob($path . '*', GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT|GLOB_BRACE);
    $files = glob($path . $pattern, $flags);
    foreach ($paths as $path) {
        $files = array_merge($files, rglob($path, $pattern, $flags));
    }

    return $files;
}

function ns($file) {
    $h = fopen($file, 'r');
    while (false !== $line = fgets($h)) {
        if (false !== stripos($line, 'namespace')) {
            $ns = trim(str_replace('namespace', '', $line), " \n;") . '\\';
            return $ns;
        }
    }

    throw new \LogicException("Couldn't parse namespace from: " . $file);
}
