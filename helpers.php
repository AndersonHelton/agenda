<?php

/**
 * Redireciona o browser para outra URL.
 *
 * @return void
 */
if (! function_exists('redirect')) {
    function redirect( $url = '/', $die = true)  
    {
        header("Location: $url");

        if ($die) {
            die();
        }
    }
}

/**
 * Debug helper function.  This is a wrapper for var_dump() that adds
 * the <pre /> tags, cleans up newlines and indents before output.
 *
 * @param  mixed  $var   The variable to dump.
 * @param  string $label OPTIONAL Label to prepend to output.
 * @param  bool   $die  OPTIONAL Die script execution.
 * @return string
 */
if (! function_exists('dd')) {
    function dd($var, $label = null, $die = true)
    {
        // format the label
        $label = ($label===null) ? '' : rtrim($label) . ' ';

        // var_dump the variable into a buffer and keep the output
        ob_start();
        var_dump($var);
        $output = ob_get_clean();

        // neaten the newlines and indents
        $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);

        if (PHP_SAPI === 'cli') {
            $output = PHP_EOL . $label
                    . PHP_EOL . $output
                    . PHP_EOL;
        } else {
            $output = '<pre>'
                    . $label
                    . $output
                    . '</pre>';
        }

        echo $output;

        if ($die) {
            exit;
        }
    }
}

/**
 * Retorna o container de configurações.
 *
 * @return array
 */
if (! function_exists('config')) {
    function config( $section = '')
    {
        $config = require __DIR__ . '/config.php';

        if ($section !== '' && isset($config[$section])) {
            $config = $config[$section];
        }

        return $config;
    }
}

/**
 * Gera uma parte hexadecimal de uma cor.
 *
 * @return string
 */
if (! function_exists('random_color_part')) {
    function random_color_part() {
        return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
    }
}

/**
 * Gera uma cor hexadecimal aleatória.
 * 
 * @return string
 */
if (! function_exists('random_color')) {
    function random_color() {
        return random_color_part() . random_color_part() . random_color_part();
    }
}
