<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';

    $baseDir = __DIR__ . '/';

    $len = mb_strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = mb_substr($class, $len);
    $relativeClass = explode('\\', $relativeClass);
    $classname = array_pop($relativeClass);
    array_walk($relativeClass, function (&$item) {
        $item = mb_strtolower($item);
    });
    $relativeClass[] = $classname;
    $relativeClass = join('/', $relativeClass);

    $file = $baseDir . $relativeClass . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
