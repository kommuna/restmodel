<?php

namespace RestModel\Controllers;

use RestModel\Exceptions;

class Controller {

    protected static function getControllerName($controllerName) {
        return "{$controllerName}Controller";
    }

    public static function init($controllerName, $actionName) {

        $controllerName = static::getControllerName($controllerName);
        $controller = new $controllerName();

        if(!method_exists($controller, $actionName) && !is_callable([$controller, $actionName])) {
            throw new Exceptions\BadRequest404("Invalid URL");
        }

        return [$controller, $actionName];

    }




}