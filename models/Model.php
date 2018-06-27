<?php

namespace App\Models;

use PDO;
use App\Database\DatabaseORM;

class Model extends DatabaseORM
{
    /**
     * Construtor da classe.
     *
     * @return void
     */
    public function __construct(array $data = [])
    {
        $config = config('database');
        parent::connectDb('sqlite:' . $config['database']);
        parent::__construct($data);
    }
}
