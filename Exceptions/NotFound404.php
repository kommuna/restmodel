<?php

namespace RestModel\Exceptions;

class NotFound404 extends APIException {
    protected $httpCode = 404;
    protected $message = 'Not Found';
}
