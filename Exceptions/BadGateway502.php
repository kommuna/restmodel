<?php

/**
The server, while acting as a gateway or proxy, received an invalid response from the upstream server
*
 it accessed in attempting to fulfill the request.

The server was acting as a gateway or proxy and received an invalid response from the upstream server.
 */

namespace RestModel\Exceptions;

class BadGateway502 extends APIException {
    protected $httpCode = 502;
    protected $message = 'Internal Server Error';
}
