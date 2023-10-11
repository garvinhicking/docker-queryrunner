<?php

$dbs = [
    'mysql'     =>  [
        '5.7',
        '8.0', '8.1'
    ],
    'mariadb'   =>  [
        '10.2', '10.3', '10.4', '10.5', '10.6', '10.7', '10.8', '10.9', '10.10', '10.11',
        '11.0', '11.1'
    ],
    'postgres'  =>  [
        '10', '11', '12', '13', '14', '15', '16'
    ]
];

$ports = [
    'mysql'     => 13306,
    'mariadb'   => 14306,
    'postgres'  => 15432,
    'sqlite'    => 11337,
];

$pdoMap = [
    'mysql'     => 'mysql',
    'mariadb'   => 'mysql',
    'sqlite'    => 'sqlite',
    'postgres'  => 'pgsql',
];

$rootPassword = 'root';
$dockerPrefix = basename(__DIR__);
$dockerSuffix = '-1';

if (is_array($argv)) {
    if (isset($argv[1])) {
        $dockerPrefix = $argv[1];
    }

    if (isset($argv[2])) {
        $dockerSuffix = $argv[2];
    }
}

$mode = 'doctrine'; // doctrine|pdo (deprecated)
