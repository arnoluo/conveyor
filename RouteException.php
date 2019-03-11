<?php

namespace Conveyor;

class RouteException extends \LogicException
{
    public function __construct($message = "", $code = 0)
    {
        $message = 'Conveyor\RouteException: ' . $message;
        parent::__construct($message, $code);
        set_exception_handler(function (RouteException $ex) {
            echo $ex->getMessage();
        });
    }
}