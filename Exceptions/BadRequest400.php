<?php

namespace RestModel\Exceptions;

/**
 * The request could not be understood by the server due to malformed syntax. The client SHOULD NOT
 * repeat the request without modifications.
 *
 * The request cannot be fulfilled due to bad syntax.
 *
 * General error when fulfilling the request would cause an invalid state.
 * Domain validation errors, missing data, etc. are some examples.
 * http://www.restapitutorial.com/httpstatuscodes.html
 */

class BadRequest400 extends APIException {
    protected $httpCode = 400;
    protected $message = 'Bad Request';

}
