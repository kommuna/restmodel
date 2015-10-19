<?php

namespace RestModel\Core;

use Slim\Slim;

class apiParams {

    static protected $app;

    protected $offset;
    protected $limit;
    protected $filter = [];
    protected $order = [];

    static protected function getApp() {

        if(!self::$app) {
            self::$app = Slim::getInstance();
        }

        return self::$app;
    }

    static public function getParams() {

        $app = self::getApp();

        $limit = (int)$app->request->get('limit');

        $maxLimit = $app->appConfig['app']['maxLimitListing'];

        if($limit <= 0) {

            $limit = $maxLimit;

        } elseif($limit > $maxLimit) {

            \RestModel\Exceptions\BadRequest400::throwException("Value of parameter 'limit' > ".$app->appConfig['app']['maxLimitListing']);
        }

        $offset = (int)$app->request->get('offset');

        if($offset < 0) {
            \RestModel\Exceptions\BadRequest400::throwException("'offset' should be positive or 0");
        }

        $self = new self();
        $self->setOffset($offset)->setLimit($limit)
            ->setFilter(self::parseJSONParams('filter'))
            ->setOrder(self::parseJSONParams('order'));

        return $self;

    }

    static public function parseJSONParams($name) {

        $params = self::getApp()->request->get($name);

        if(!$params) {
            return [];
        }

        $params = json_decode($params, true);

        if(json_last_error()) {
            \RestModel\Exceptions\BadRequest400::throwException("'$name' JSON data is invalid!");
        }

        if(!is_array($params)) {
            \RestModel\Exceptions\BadRequest400::throwException("'$name' JSON should be object");
        }

        return $params;

    }



    public function setOffset($value) {
        $this->offset = $value;
        return $this;
    }

    public function getOffset() {
        return $this->offset;
    }

    public function getLimit() {
        return $this->limit;
    }

    public function setLimit($value) {
        $this->limit = $value;
        return $this;
    }

    public function getFilter() {
        return $this->filter;
    }

    public function setFilter($value) {
        $this->filter = $value;
        return $this;
    }

    public function getOrder() {
        return $this->order;
    }

    public function setOrder($value) {
        $this->order = $value;
        return $this;
    }
}