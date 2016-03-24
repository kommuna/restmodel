<?php

namespace RestModel\Controllers;

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use RestModel\Exceptions\BadRequest400;
use RestModel\Exceptions\InternalServerError500;
use RestModel\Exceptions\APIException;
use Slim\Slim;
use PDO;
use PDOStatement;

class Controller {

    public function __construct() {

        $this->app = \Slim\Slim::getInstance();

    }

    public function __($value) {

        if(property_exists($this, 'app') && property_exists($this->app, 'log')
            && method_exists($this->app->log, 'addDebug')) {
            $this->app->log->addDebug($value);
        } else {
            error_log($value);
        }

    }

    protected static function getControllerName($controllerName) {
        return "{$controllerName}Controller";
    }

    public static function init($controllerName, $actionName) {

        $controllerName = static::getControllerName($controllerName);
        $controller = new $controllerName();

        if(!method_exists($controller, $actionName) && !is_callable([$controller, $actionName])) {
            throw new BadRequest400("Invalid URL");
        }

        return [$controller, $actionName];

    }

    static protected function registerWhoops() {

        $app = Slim::getInstance();

        $app->container->singleton('whoopsPrettyPageHandler', function() {
            return new PrettyPageHandler();
        });

        $app->whoopsSlimInfoHandler = $app->container->protect(function() use ($app) {

            try {
                $request = $app->request();
            } catch (\RuntimeException $e) {
                return;
            }

            $current_route = $app->router()->getCurrentRoute();
            $route_details = array();

            if ($current_route !== null) {
                $route_details = array(
                    'Route Name'       => $current_route->getName() ?: '<none>',
                    'Route Pattern'    => $current_route->getPattern() ?: '<none>',
                    'Route Middleware' => $current_route->getMiddleware() ?: '<none>',
                );
            }

            $app->whoopsPrettyPageHandler->addDataTable('Slim Application', array_merge(array(
                'Charset'          => $request->headers('ACCEPT_CHARSET'),
                'Locale'           => $request->getContentCharset() ?: '<none>',
                'Application Class'=> get_class($app)
            ), $route_details));

            $app->whoopsPrettyPageHandler->addDataTable('Slim Application (Request)', array(
                'URI'         => $request->getRootUri(),
                'Request URI' => $request->getResourceUri(),
                'Path'        => $request->getPath(),
                'Query String'=> $request->params() ?: '<none>',
                'HTTP Method' => $request->getMethod(),
                'Script Name' => $request->getScriptName(),
                'Base URL'    => $request->getUrl(),
                'Scheme'      => $request->getScheme(),
                'Port'        => $request->getPort(),
                'Host'        => $request->getHost(),
            ));
        });
        // Open with editor if editor is set
        $whoops_editor = $app->config('whoops.editor');
        if ($whoops_editor !== null) {
            $app->whoopsPrettyPageHandler->setEditor($whoops_editor);
        }
        $app->container->singleton('whoops', function() use ($app) {
            $run = new Run();
            $run->pushHandler($app->whoopsPrettyPageHandler);
            $run->pushHandler($app->whoopsSlimInfoHandler);
            return $run;
        });

    }

    static public function notFound() {
        $app = Slim::getInstance();
        $params = $app->request->get();
        $app->log->addWarning('Not found url: ' . $app->request->getPathInfo() . ($params ? " GET params: ". print_r($params,1) : ''));
    }

    static public function error(\Exception $e) {

        $app = Slim::getInstance();


        if($e instanceof APIException) {

            $app->log->addError("API error [{$e->getHTTPCode()}][{$e->getCode()}]: ".print_r(['error' => $e->getErrors()],1));
            $app->halt($e->getHTTPCode(), json_encode(['error' => $e->getErrors()], JSON_FORCE_OBJECT));


        } else {

            $app->log->addError("API error [500][{$e->getCode()}]: {$e->getMessage()} \n {$e->getTraceAsString()}");
            $app->syslog->addError("API error [500][{$e->getCode()}]: {$e->getMessage()} \n {$e->getTraceAsString()}");


            if($app->config('mode') == 'development') {

                self::registerWhoops();
                $app->whoops->handleException($e);

            }

            $app->halt(500);

        }

    }

    public function fetch($template, $params = []) {
        $this->app->view->appendData($params);
        return $this->app->view->fetch($template, $params);
    }

    public function render($template, $params = []) {
        $this->app->render($template, $params);
    }

    public function sendCSVFile($csv, $outputName = 'file.csv', $columnNames = []) {

        $this->app->response->headers->set('Content-type', 'application/csv');
        $this->app->response->headers->set('Content-Disposition', 'attachment; filename="'.$outputName.'"; modification-date="'.date('r').'";');

        if($csv instanceof PDOStatement) {

            if (!($output = fopen("php://output", 'w'))) {
                InternalServerError500::throwException("Can't open output stream");
            }

            $flag = false;
            while ($row = $csv->fetch(PDO::FETCH_ASSOC)) {

                if (!$flag) {
                    $columnNames = $columnNames ?: array_keys($row);
                    fputcsv($output, $columnNames);
                    $flag = true;
                }
                fputcsv($output, $row);
            }
            if (!fclose($output)) {
                InternalServerError500::throwException("Can't close php://output");
            }

        } elseif (is_array($csv)) {

            if (!($output = fopen("php://output", 'w'))) {
                InternalServerError500::throwException("Can't open output stream");
            }

            if($columnNames) {
                fputcsv($output, $columnNames);
            }

            foreach($csv as $row) {
                fputcsv($output, $row);
            }
            if (!fclose($output)) {
                InternalServerError500::throwException("Can't close php://output");
            }

        } else {
            $this->app->response->headers->set('Content-Length', strlen($csv));
            $this->app->response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            $this->app->response->headers->set('Pragma', 'no-cache');
            $this->app->response->headers->set('Expires', '0');
            $this->app->halt(200, $csv);
        }


    }

}