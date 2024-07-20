<?php

namespace Rumput;

use Exception;

class RouterException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message = "", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->message = 'Router: \'' . $message . '\'. Error!';
    }
}
