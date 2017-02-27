<?php

class DB
{
    public function get()
    {
        $host = "localhost";
        $dbname = "cheboksa_freest";
        $charset = "UTF8";
        $user = "root";
        $pass = "";

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $pdo = new PDO($dsn, $user, $pass);

        return $pdo;
    }
}
