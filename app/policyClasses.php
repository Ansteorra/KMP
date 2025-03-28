<?php

$paths = [];

// Include the app policy folder.
$appPolicyPath = realpath(__DIR__ . '/src/Policy');
if ($appPolicyPath !== false) {
    $paths[] = $appPolicyPath;
}

// Include all plugin policy folders.
$pluginPolicyDirs = glob(__DIR__ . '/plugins/*/src/Policy', GLOB_ONLYDIR);
foreach ($pluginPolicyDirs as $dir) {
    $realDir = realpath($dir);
    if ($realDir !== false) {
        $paths[] = $realDir;
    }
}

$policyClasses = [];

foreach ($paths as $path) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $contents = file_get_contents($file->getPathname());

            // Extract the namespace.
            if (preg_match('/namespace\s+([^;]+);/', $contents, $nsMatch)) {
                $namespace = trim($nsMatch[1]);
            } else {
                $namespace = '';
            }

            // Extract the class name.
            if (preg_match('/class\s+([^\s{]+)/', $contents, $classMatch)) {
                $className = trim($classMatch[1]);
            } else {
                continue;
            }

            // Build fully qualified name if a namespace was found.
            $fullClassName = $namespace ? $namespace . '\\' . $className : $className;
            $policyClasses[] = $fullClassName;
        }
    }
}

print_r($policyClasses);