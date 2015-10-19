<?php

/**
 * The server encountered an unexpected condition which prevented it from fulfilling the request.

A generic error message, given when no more specific message is suitable.

The general catch-all error when the server-side throws an exception.
 */

namespace RestModel\Exceptions;

class InternalServerError500 extends APIException {
    protected $httpCode = 500;
    protected $message = 'Internal Server Error';
}
