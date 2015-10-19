<?php

namespace RestModel\Exceptions;

/**
 * 451 Unavailable For Legal Reasons (Internet draft)
 * Defined in the internet draft "A New HTTP Status Code for Legally-restricted Resources".[22]
 * Intended to be used when resource access is denied for legal reasons,
 * e.g. censorship or government-mandated blocked access.
 * A reference to the 1953 dystopian novel Fahrenheit 451, where books are outlawed.[23]
 */



class Unavalable451 extends APIException {
    protected $httpCode = 451;
    protected $message = 'Unavailable For Legal Reasons';
}
