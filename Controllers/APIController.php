<?php

namespace RestModel\Controllers;


use RestModel\Core\apiParams;
use RestModel\Exceptions\BadRequest400;
use RestModel\Exceptions\NotFound404;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use RestModel\Exceptions\InternalServerError500;


class APIController extends Controller {

    protected $app;
    protected $model;
    protected $transformer;


    protected static function getControllerName($controllerName) {
        return "API{$controllerName}Controller";
    }

    public function response($data, $totalCount = false, $query = false, $statusCode = 200) {

        if($this->transformer) {
            $fractal = new Manager();

            if($totalCount === false) {
                $resource = new Item($data, $this->transformer);
            } else {
                $resource = new Collection($data, $this->transformer);
            }

            $response = $fractal->createData($resource)->toArray();


        } else {
            $response = ['data' => $data];
        }

        if($totalCount !== false) {
            $response['totalCount'] = (int) $totalCount;
        }

        if($query !== false) {
            $response['search'] = $query;
        }

        $response = json_encode($response, JSON_UNESCAPED_UNICODE);

        if($response === false) {
            InternalServerError500::throwException("Response couldn't encoded as JSON");
        }

        $this->app->response->status($statusCode);
        $this->app->response->body($response);
    }

    public function responseCollection($data, $count) {

    }

    public function __construct() {

        $this->app = \Slim\Slim::getInstance();

        $this->app->response->headers->set('Accept', 'application/json');
        $this->app->response->headers->set('Accept-Charset', 'utf-8');
        $this->app->response->headers->set('Content-Type', 'application/json;charset=utf-8');
        $this->app->response->headers->set('Access-Control-Allow-Origin', '*');
        $this->app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->app->response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept');
        $this->app->response->headers->set('X-MediaService-Time', date('c'));
        $this->app->response->headers->set('X-MediaService-Version', '1.0');
    }

    public function getList(apiParams $params = null) {

        $params = $params ? $params : apiParams::getParams();
        $count = $this->model->getTotalCount($params);

        if($params->getOffset() < 0) {
            BadRequest400::throwException("'offset' out of range");
        }
        $rows = $this->model->getMany($params);

        $this->response($rows, $count);

    }

    protected function decodeJSON($jsonString) {

        if(!$jsonString) {
            BadRequest400::throwException('Body request is empty!');
        }

        $json = json_decode($jsonString, true);

        if(json_last_error()) {
            BadRequest400::throwException('Request JSON data is invalid!');
        }

        return $json;

    }



    public function getItem($id, $statusCode = 200) {

        $item = $this->model->getById($id);

        if(!$item) {
            NotFound404::throwException("Item with id = $id doesn't exist!");
        }
        $this->response($item, false, false, $statusCode);
    }

    public function addItem() {


        $body = $this->decodeJSON($this->app->request->getBody());

        if(isset($body['id'])) {
            unset($body['id']);
        }

        return $this->model->setValues($body)->validateValues()->save();

    }

    public function isItemExist($id) {

        $item = $this->model->getById($id);
        if(!$item) {
            NotFound404::throwException("Item with id = $id doesn't exist!");
        }

        return $item;
    }

    public function updateItem($id, $body = []) {

        $body = $body ? $body : $this->decodeJSON($this->app->request->getBody());


        $body['id'] = $id;

        $this->isItemExist($id);

        $this->model->setValues($body, true)->validateValues()->save();

    }

    public function deleteItem($id) {

        $this->isItemExist($id);
        $this->model->delete($id);
        $this->app->halt(204);

    }

    public function markItemAsDeleted($id) {

        $this->isItemExist($id);
        $this->model->markAsDeleted($id);
        $this->app->halt(204);

    }
}