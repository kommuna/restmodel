<?php

namespace RestModel\Models;

use \RestModel\Exceptions\ModelException;
use \SolrClient;
use \SolrQuery;

class SolrModel {

    protected $validators = [];
    protected $client;
    protected $logger;
    protected $totalResultSetCount = 0;
    protected $fields = [];

    public static function escapeSolrValue($string) {

        $match = array('\\', '+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', ' ');
        $replace = array('\\\\', '\\+', '\\-', '\\&', '\\|', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\~', '\\*', '\\?', '\\:', '\\"', '\\;', '\\ ');
        $string = str_replace($match, $replace, $string);

        return $string;
    }

    public static function convertFilterStrValue($string) {

        if(!$string || !is_string($string)) {
            return $string;
        }

        $firstChar = mb_substr($string, 0, 1);

        if($firstChar !== false) {
            $string = $firstChar == '%' ? mb_substr($string, 1) : "^$string";
        }

        $lastChar = mb_substr($string, -1, 1);

        if($lastChar !== false) {
            $string = $lastChar == '%' ? mb_substr($string, -1) : "$string$";
        }

        return '"'.$string.'"';
    }


    public function __construct($config, $logger) {

        $this->logger = $logger;
        $this->client = new SolrClient($config);

    }

    public function setFieldsValidators($validators) {
        $this->validators = $validators;
        return $this;
    }

    public function getFieldsValidators() {
        return $this->validators;
    }

    public function addFields($fields) {
        $this->fields = $fields;
        return $this;
    }

    public function getMany($params = null) {

        $query = new SolrQuery();

        foreach($this->fields as $f) {
            $query->addField($f);
        }

        $q = $params && $params->getQuery() ? self::escapeSolrValue(trim($params->getQuery())) : false;
        $q = $q ? $q : "*:*";

        $query->setQuery($q);

        if($params && $params->getOffset()) {
            $query->setStart($params->getOffset());
        }

        if($params && $params->getLimit()) {
            $query->setRows($params->getLimit());
        }

        $this->applyFilter($query, $params);

        $this->applyOrder($query, $params);


        try {
            $queryResponse = $this->client->query($query);
        } catch(\SolrClientException $e) {
            $this->logger->addDebug(print_r($e->getInternalInfo(), 1));
            throw $e;
        }

        if($queryResponse->success()) {
            $response = $queryResponse->getResponse();
            $this->totalResultSetCount = (int)$response['response']['numFound'];
            $response = $response['response']['docs'];

        } else {
            $response = [];
        }

        return $response;
    }


    public function getTotalCount($params = null) {
        return $this->totalResultSetCount;
    }


    protected function applyFilter(\SolrQuery $solrQuery, $params = null)
    {

        if (is_null($params)) {
            return $solrQuery;
        }

        $filters = $params->getFilter();
        $fields = $this->getFieldsValidators();

        foreach ($filters as $filter) {

            foreach (array_keys($fields) as $field) {

                if (!array_key_exists($field, $filter)) {
                    continue;
                } else {
                    $fieldParams = self::escapeSolrValue($filter[$field]);
                    $fieldParams = self::convertFilterStrValue($fieldParams);
                }

                if (is_array($fieldParams) && array_key_exists('not', $fieldParams) && !is_array($fieldParams['not'])) {
                    $fieldParams['not'] = [$fieldParams['not']];
                }

                if (is_array($fieldParams)) {
                    if (isset($fieldParams['not'])) {
                        foreach ($fieldParams['not'] as $value) {
                            if (is_null($value)) {
                                $solrQuery->addFilterQuery("$field:[* TO *]");
                            } else {
                                $solrQuery->addFilterQuery("!$field:$value");
                            }
                        }

                    } else {

                        $from = $to = false;

                        if (isset($fieldParams['from']) && is_scalar($fieldParams['from'])) {

                            // Date fields should end by '_on' (posted_on)
                            if (substr($field, -3) == '_on') {

                                $time = strtotime($fieldParams['from']);

                                $from = $time !== false ? date("c", $time) . 'Z' : false;
                            } else {
                                $from = $fieldParams['from'];
                            }
                        }


                        if (isset($fieldParams['to']) && is_scalar($fieldParams['to'])) {

                            if (substr($field, -3) == '_on') {
                                $time = strtotime($fieldParams['to']);
                                $to = $time !== false ? date("c", $time) . 'Z' : false;
                            } else {
                                $to = $fieldParams['to'];
                            }
                        }

                        if ($from !== false && $to !== false) {

                            $solrQuery->addFilterQuery("$field:[$from TO $to]");

                        } elseif ($from !== false) {

                            $solrQuery->addFilterQuery("$field:[$from TO *]");

                        } elseif ($to !== false) {

                            $solrQuery->addFilterQuery("$field:[* TO $to]");

                        } else {
                            $solrQuery->addFilterQuery("$field:(" . implode(' OR ', $fieldParams) . ")");

                        }
                    }


                } else {


                    //http://stackoverflow.com/questions/4238609/how-to-query-solr-for-empty-fields
                    if (is_null($fieldParams)) {
                        $solrQuery->addFilterQuery("-$field:[* TO *]");
                    } // Logical fields should start by 'is_' (is_logo_on)
                    elseif (substr($field, 0, 3) == 'is_') {

                        $fieldParams = $fieldParams ? 'true' : 'false';


                        $solrQuery->addFilterQuery("$field:$fieldParams");
                    } // Date fields should end by '_on' (posted_on)
                    elseif (substr($field, -3) == '_on') {

                        $time = strtotime($fieldParams);
                        $fieldParams = $time !== false ? date("c", $time) . 'Z' : false;

                    } else {
                        $solrQuery->addFilterQuery("$field:$fieldParams");
                    }


                }
            }
        }

        error_log(print_r($solrQuery->getFilterQueries(),1));

        return $solrQuery;
    }

    protected function applyOrder(\SolrQuery $solrQuery, $params = null) {

        if(is_null($params)) {
            return $solrQuery;
        }

        $orders = $params->getOrder();

        foreach($orders as $order) {

            if(!is_array($order)) {
                ModelException::throwException("Wrong 'order' parameter");
            }

            $orderField = array_keys($order);

            if(!is_array($orderField) || !isset($orderField[0])) {
                ModelException::throwException("Wrong 'order' parameter");
            }

            $orderField = $orderField[0];

            $fields = $this->getFieldsValidators();

            if($orderField === 'random' && $order[$orderField]) {
                $randStr = substr(preg_replace("/[^a-zA-Z0-9]/", "", $order[$orderField]), 0, 16);
                $solrQuery->addSortField("{$orderField}_{$randStr}", \SolrQuery::ORDER_DESC);
            }

            if(!isset($fields[$orderField])) {
                continue;
            }

            if(strtolower($order[$orderField]) == 'asc') {
                $solrQuery->addSortField($orderField, \SolrQuery::ORDER_ASC);
            }

            if(strtolower($order[$orderField]) == 'desc') {
                $solrQuery->addSortField($orderField, \SolrQuery::ORDER_DESC);
            }
        }

        return $solrQuery;
    }


}