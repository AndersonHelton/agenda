<?php

namespace App;

class Application
{
    /**
     * Inicia a aplicação.
     *
     * @return void
     */
    public function run()
    {
        require_once 'helpers.php';
        require_once 'autoload.php';

        $route = $this->parseRoute();

        $controller = $this->controller($route['controller']);
        $response = $this->action($controller, $route['action']);

        echo $response;
    }

    /**
     * Processa a rota acessada.
     *
     * @return string
     */
    private function parseRoute()
    {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = explode('?', $uri);
        $uri = rtrim($uri[0], '/');

        if ($uri === '') {
            $uri = '/';
        }

        $routes = require 'routes.php';
        $route = $routes[$uri];

        if ($route === null) {
            http_response_code(404);
            echo '404';
            die();
        }

        $route = explode('@', $route);
        $route = [
            'controller' => $route[0],
            'action'     => $route[1],
        ];

        return $route;
    }

    /**
     * Instancia o controlador para a rota corrente.
     *
     * @param  string $classname
     * @return Object
     */
    private function controller($classname)
    {
        $classname = 'App\\Controllers\\' . $classname;

        return new $classname;
    }

    /**
     * Chama a ação que executará a requisição.
     *
     * @param  Object $controller
     * @param  string $action
     * @return string
     */
    private function action($controller, $action)
    {
        $params = [];
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        if ($method === 'GET') {
            $params = $_GET;
        } elseif ($method === 'POST') {
            $params = array_merge($_POST, $_GET);
        }

        return $controller->{$action}($params);
    }
}

$app = new Application;
$app->run();
