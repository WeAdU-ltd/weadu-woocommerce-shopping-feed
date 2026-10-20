<?php
// Tiny assertion helpers shared by the module tests. No framework needed.

$GLOBALS['weadu_failures'] = 0;
$GLOBALS['weadu_passes'] = 0;
$GLOBALS['weadu_deprecations'] = array();

set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        $key = basename($file) . ':' . $line . ' ' . $message;
        $GLOBALS['weadu_deprecations'][$key] = true;
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check($name, $condition, $detail = '')
{
    if ($condition) {
        $GLOBALS['weadu_passes']++;
        fwrite(STDOUT, "ok - " . $name . PHP_EOL);
        return;
    }
    $GLOBALS['weadu_failures']++;
    fwrite(STDOUT, "not ok - " . $name . ($detail !== '' ? ' (' . $detail . ')' : '') . PHP_EOL);
}

function contains($haystack, $needle)
{
    return strpos((string) $haystack, (string) $needle) !== false;
}

function finish()
{
    foreach (array_keys($GLOBALS['weadu_deprecations']) as $line) {
        fwrite(STDOUT, "# TODO deprecation: " . $line . PHP_EOL);
    }
    fwrite(STDOUT, '# pass ' . $GLOBALS['weadu_passes'] . PHP_EOL . '# fail ' . $GLOBALS['weadu_failures'] . PHP_EOL);
    exit($GLOBALS['weadu_failures'] > 0 ? 1 : 0);
}

// A known issue that should be fixed in the module, reported without failing the run.
function todo($name, $stillBroken)
{
    if ($stillBroken) {
        fwrite(STDOUT, "# TODO " . $name . PHP_EOL);
    } else {
        check($name . ' (fixed: turn this todo into a check)', true);
    }
}
