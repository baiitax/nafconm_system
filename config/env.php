<?php

function app_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    return $_ENV[$name] ?? $_SERVER[$name] ?? $default;
}

function app_env_required(string $name, bool $allow_empty = false): string
{
    $value = app_env($name);
    if ($value === null || (!$allow_empty && $value === '')) {
        throw new RuntimeException('Required environment variable is missing: ' . $name);
    }

    return $value;
}

$env_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_readable($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if (
            !preg_match('/\A[A-Z][A-Z0-9_]*\z/', $name)
            || getenv($name) !== false
            || array_key_exists($name, $_ENV)
            || array_key_exists($name, $_SERVER)
        ) {
            continue;
        }

        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[strlen($value) - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}