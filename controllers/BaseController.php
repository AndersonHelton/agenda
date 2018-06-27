<?php

namespace App\Controllers;

class BaseController
{
    /**
     * Retorna o conteúdo de uma determinada view.
     *
     * @param  string $template
     * @param  array $data
     * @return string
     */
    public function view($template, $data = [])
    {
        ob_start();
        //dd($data) ;
        extract($data);
        require __DIR__ . '/../views/' . $template . '.phtml';

        $view = ob_get_contents();
        ob_end_clean();

        return $view;
    }
}
