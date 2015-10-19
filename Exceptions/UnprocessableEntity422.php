<?php

namespace RestModel\Exceptions;



class UnprocessableEntity422 extends APIException {
    protected $httpCode = 422;
    protected $message = 'Unprocessable Entity';
}