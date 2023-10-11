<?php
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

// Parses fixture data into usable array
$fp = fopen($task . '/fixture.csv', 'rb');
$fixture = [];
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

// Inject sqlite (not a docker instance)
$dbs['sqlite'] = ['base'];

foreach($dbs AS $dbType => $dbVersions) {
	foreach($dbVersions AS $dbVersion) {
        $docker_name = $dockerPrefix . '-' . $dbType . str_replace('.', '-', $dbVersion);
        $docker_port = $ports[$dbType];
        $pdoDriver = $pdoMap[$dbType];

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
            $config = new \Doctrine\DBAL\Configuration();
            $connectionParams = [
                'user'     => 'root',
                'password' => $rootPassword,
                'dbname'   => ($pdoDriver === 'pgsql' ? 'root' : 'mysql'),
                'host'     => '127.0.0.1',
                'port'     => $docker_port,
                'driver'   => 'pdo_' . $pdoDriver,
            ];
            /** @var \Doctrine\DBAL\Connection $conn */

            try {
            $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
            } catch (Exception $e) {
                echo "[X] Setup connect fail.\n";
                print_r($e);
                continue;
            }

            if (!$conn) {
                echo "[X] Setup connect fail (object).\n";
                continue;
            }

            try {
                $conn->connect();
            } catch (Exception $e) {
                echo "[X] Connect fail.\n";
                print_r($e);
                continue;
            }

            if ($conn) {
                echo "[.] Connected.\n";

                if ($pdoDriver === 'sqlite') {
                    $statement = $conn->executeQuery('SELECT sqlite_version() AS version');
                } else {
                    $statement = $conn->executeQuery('SELECT VERSION()');
                }

                $version = $statement->fetchOne();
                echo "[.] Version: " . $version . "\n";
                $sm = $conn->createSchemaManager();

                try {
                    $databases = $sm->listDatabases();
                    echo "[.] Databases: " . print_r($databases,1) . "\n";
                } catch (Exception $e) {
                    echo "[x] Database list not supported.\n";
                }
            }

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
            echo "[.] Inserting " . count($fixture) . " fixture rows into $schemaName.\n";
            foreach($fixture AS $fixtureRow) {
                try {
                    $conn->insert($schemaName, $fixtureRow);
                } catch (Exception $e) {
                    echo "[x] INSERT failure.";
                    print_r($e);
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

        $ports[$dbType]++;
    }
}

$baseline = $resultOutput[0]['raw'];

foreach($resultOutput AS $outRow) {
    echo $outRow['out'] . ': ' . ($baseline === $outRow['raw'] ? ' SUCCESS' : 'FAIL') . "\n";
}
