<?php

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

require 'vendor/autoload.php';
require 'config.inc.php';

if (!class_exists('PDO')) {
    die('Requires PDO' . "\n");
}

$_PDOavail = PDO::getAvailableDrivers();
$PDOavail  = [];
foreach($_PDOavail AS $driver) {
    $PDOavail[$driver] = $driver;
}

$resultOutput = [];

if (!is_array($argv) || !isset($argv[1])) {
    die('Please specify the task subdirectory name as first parameter.' . "\n");
}

$task = 'tasks/' . $argv[1];
if (!is_dir($task)) {
    die('Task ' . $task . ' not found.' . "\n");
}

if (!file_exists($task . '/query.php')) {
    die('Task subdirectory must hold query.php, init.php and fixture.csv.' . "\n");
}

function getFixture(string $filename): array
{
    $fixture = [];

    if (!file_exists($filename)) {
        return $fixture;
    }

    // Parses fixture data into usable array
    $fp = fopen($filename, 'rb');
    $fixtureHead = null;
    $row = 0;
    while (($data = fgetcsv($fp, null, ",", "'", '\\')) !== false) {
        $row++;
        $singleFixture = [];

        if ($fixtureHead === null) {
            // First line is headers
            $fixtureHead = [];
            foreach($data AS $headerPart) {
                $fixtureHead[] = str_replace('`', '', trim($headerPart));
            }

            // Ignores first line, no actual data
            continue;
        }

        if (count($data) == 0) {
            continue;
        }

        if (count($data) !== count($fixtureHead)) {
            echo "[ERROR] CSV mismatch in row " . $row . " of fixture (skipping row):\n";
            print_r($data);
            continue;
        }

        foreach($data AS $idx => $col) {
            $singleFixture[$fixtureHead[$idx]] = trim($col);
        }
        $fixture[] = $singleFixture;
    }

    return $fixture;
}

function run($dbType, $dbVersion, &$ports, bool $strict = false): void {
    global $dockerPrefix, $pdoMap, $mode, $rootPassword, $task, $fixture, $resultOutput, $PDOavail;

    $docker_name = $dockerPrefix . '-' . $dbType . str_replace('.', '-', $dbVersion);
    if ($strict) {
        $docker_name .= '-strict';
        $docker_port = $ports[$dbType . '-strict'];
        $pdoDriver = $pdoMap[$dbType . '-strict'];
    } else {
        $docker_port = $ports[$dbType];
        $pdoDriver = $pdoMap[$dbType];
    }

    echo "[+] Connecting to $docker_name : $docker_port via $mode [$pdoDriver]\n";

    if (!isset($PDOavail[$pdoDriver])) {
        echo "[X] Missing PDO driver $pdoDriver\n";
    }

    if ($mode === 'pdo') {
        // Deprecated
        $db = new PDO("$pdoDriver:host=127.0.0.1;port=$docker_port", 'root', $rootPassword);

        if (is_object($db)) {
            echo "[.] Connected.\n";
            if ($pdoDriver === 'sqlite') {
                $version = $db->query("SELECT sqlite_version() AS version")->fetch();
            } else {
                $version = $db->query("SELECT VERSION()")->fetch();
            }
            echo "[.] Version: " . $version[0] . "\n";
        }

        // Provision
        $db->exec(file_get_contents('init.sql'));
        echo "[.] Provisioned with init.sql\n";

        $query = file_get_contents('query.sql');
        echo "[.] QUERY: " . $query . "\n";
        $result = $db->query($query);
        $rows = 0;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows++;
            echo "[" . $rows . "] ";
            print_r($row);
            echo "\n";
        }
    } elseif ($mode === 'doctrine') {
        $config = new Configuration();
        $connectionParams = [
            'user'     => 'root',
            'password' => $rootPassword,
            'dbname'   => ($pdoDriver === 'pgsql' ? 'root' : 'mysql'),
            'host'     => '127.0.0.1',
            'port'     => $docker_port,
            'driver'   => 'pdo_' . $pdoDriver,
        ];
        if ($strict) {
            $connectionParams['driverOptions'] = [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY'",
            ];
        }
        /** @var Connection $conn */

        try {
            $conn = DriverManager::getConnection($connectionParams, $config);
        } catch (Exception $e) {
            echo "[X] Setup connect fail.\n";
            print_r($e);
            return;
        }

        echo "[.] Connected.\n";

        if ($pdoDriver === 'sqlite') {
            $statement = $conn->executeQuery('SELECT sqlite_version() AS version');
        } else {
            $statement = $conn->executeQuery('SELECT VERSION()');
        }

        $version = $statement->fetchOne();
        echo "[.] Version: " . $version . "\n";
        if ($strict) {
            $statement = $conn->executeQuery('SELECT @@sql_mode');

            $strictMode = $statement->fetchOne();
            echo "[.] Strict mode enabled: " . $strictMode . "\n";
        }
        $sm = $conn->createSchemaManager();

        try {
            $databases = $sm->listDatabases();
            echo "[.] Databases: " . print_r($databases,1) . "\n";
        } catch (Exception $e) {
            echo "[x] Database list not supported.\n";
        }

        // Vars defined inside this file.
        $dropSchema = $createSchema = $addSchema = $schemaName = $queryResult = null;
        include $task . '/init.php';

        // $dropSchema, $addSchema and $tableName comes from init.php
        foreach($dropSchema as $dropSchemaSql) {
            echo "[DROP] " . $dropSchemaSql . "\n";

            try {
                $stmt = $conn->executeStatement($dropSchemaSql);
            } catch (Exception $e) {
                echo "[x] Error dropping, probably doesn't exist.\n";
            }
        }

        foreach($addSchema as $addSchemaSql) {
            echo "[ADD] " . $addSchemaSql . "\n";

            try {
                $stmt = $conn->executeStatement($addSchemaSql);
            } catch (Exception $e) {
                echo "[x] ADD failed.\n";
                print_r($e);
            }
        }

        // Insert fixture
        if (is_array($schemaName)) {
            foreach($schemaName as $singleSchemaName) {
                $fixture = getFixture($task . '/fixture-' . $singleSchemaName . '.csv');
                echo "[.] [Multi-step fixture] Inserting " . count($fixture) . " fixture rows into $singleSchemaName.\n";
                foreach($fixture AS $fixtureRow) {
                    try {
                        $conn->insert($singleSchemaName, $fixtureRow);
                    } catch (Exception $e) {
                        echo "[x] INSERT failure.";
                        print_r($e);
                    }
                }
            }
        } else {
            echo "[.] Inserting " . count($fixture) . " fixture rows into $schemaName.\n";
            foreach($fixture AS $fixtureRow) {
                try {
                    $conn->insert($schemaName, $fixtureRow);
                } catch (Exception $e) {
                    echo "[x] INSERT failure.";
                    print_r($e);
                }
            }
        }
        echo "[.] Insertion done.\n";

        echo "[.] Executing query.\n";
        include $task . '/query.php';
        echo "[.] Query done.\n";

        // Actual query execution
        $resultOutput[] = [
            'out' => $docker_name,
            'raw' => serialize($queryResult)
        ];
    }

    echo "\n";

    if ($strict) {
        $ports[$dbType. '-strict']++;
    } else {
        $ports[$dbType]++;
    }
}

$fixture = getFixture($task . '/fixture.csv');

// Inject sqlite (not a docker instance)
$dbs['sqlite'] = ['base'];

foreach($dbs AS $dbType => $dbVersions) {
	foreach($dbVersions AS $dbVersion) {
        run($dbType, $dbVersion, $ports);
        if ($dbType === 'mysql' || $dbType === 'mariadb') {
            run($dbType, $dbVersion, $ports, true);
        }
    }
}

$baseline = $resultOutput[0]['raw'];

echo "EXPECTED RESULT:\n";
$baselineOut = print_r(unserialize($baseline),1) . "\n";
echo $baselineOut;
$fp = fopen('last-result.baseline.txt', 'wb');
fwrite($fp, $baselineOut);
fclose($fp);

foreach($resultOutput AS $outRow) {
    $success = $baseline === $outRow['raw'];
    echo $outRow['out'] . ': ' . ($success ? ' SUCCESS' : 'FAIL') . "\n";

    if (!$success) {
        echo "GOT RESULT:\n";
        $resultOut = print_r(unserialize($outRow['raw']),1) . "\n";
        echo $resultOut;
        $fp = fopen('last-result.' . $outRow['out'] . '.txt', 'wb');
        fwrite($fp, $resultOut);
        fclose($fp);
    }
}
