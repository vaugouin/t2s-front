<?php
//Environment helper: a simple .env loader, with no dependency and no side effect.
//
//	include_once(__DIR__."/env.inc.php");
//	f_envinit();
//	$strvalue=env('T2S_DEFAULT_LANG','en');
//
//f_envinit() loads lib/../.env once. A variable already present in the process environment wins
//over the value written in the file. Loadable on its own: the tests use it without a database.

/**
 * Simple .env loader (no Composer, no OOP)
 * Usage:
 *   include_once(__DIR__."/env.inc.php");
 *   env_load(__DIR__."/../.env");
 *   $strvalue = env('SOME_NAME', 'default');
 */

if (!function_exists('env_load')) {

    function env_load($file, $putenv = true, $toGlobals = true, $override = false)
    {
        if (!is_readable($file)) {
            return false;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {

            $line = trim($line);

            // Skip empty lines or comments
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            // Skip invalid lines
            if (!str_contains($line, '=')) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);

            $name  = trim($name);
            $value = trim($value);

            // Remove surrounding quotes if any
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Escape sequences
            $value = str_replace(
                ["\\n", "\\r", "\\t"],
                ["\n", "\r", "\t"],
                $value
            );

            // Check if already loaded (prevents override)
            $alreadySet =
                getenv($name) !== false ||
                isset($_ENV[$name]) ||
                isset($_SERVER[$name]);

            if ($alreadySet && !$override) {
                continue;
            }

            if ($putenv) {
                putenv("$name=$value");
            }

            if ($toGlobals) {
                $_ENV[$name]    = $value;
                $_SERVER[$name] = $value;
            }
        }

        return true;
    }
}

if (!function_exists('env')) {

    function env($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}

if (!function_exists('f_envinit')) {
	function f_envinit() {
		//Purpose: Load the .env file that sits beside html/ and lib/, once per request
		//Returns: nothing. A missing file is not an error: every setting has a default.
		static $intloaded=false;
		if ($intloaded) {
			return;
		}
		$intloaded=true;
		env_load(__DIR__."/../.env");
	}
}
?>
