<?php

namespace Rumput;

use Exception;
use Medoo\Medoo;

class DB
{
    private static DB $instance;

    private Medoo $medoo;

    public static function i(): Medoo
    {
        if (DB::$instance === null) {
            throw new Exception('Database connection not defined');
        }

        return self::$instance->medoo;
    }

    public function __construct($configs)
    {
        $this->medoo = new Medoo([
            'type' => 'mysql',
            'host' => $configs['host'],
            'database' => $configs['database'],
            'username' => $configs['username'],
            'password' => $configs['password'],
        ]);

        self::$instance = $this;
    }

    public function getMedoo(): Medoo
    {
        return $this->medoo;
    }
}
