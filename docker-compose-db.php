<?php
$docker_compose = "version: '2.3'
services:
";

require 'config.inc.php';

foreach($dbs AS $dbType => $dbVersions) {
	foreach($dbVersions AS $dbVersion) {
		$initDir = './files/' . $dbType . '-' . $dbVersion;
		if (!is_dir($initDir)) {
			mkdir($initDir);
		}

  		$docker_compose .= '  ' . $dbType . str_replace('.', '-', $dbVersion) . ':' . "\n";

		switch($dbType) {
			case 'postgres':
				$docker_compose .= '    image: postgres:' . $dbVersion . '-alpine
    restart: "always"
    environment:
      POSTGRES_PASSWORD: ' . $rootPassword . '
      POSTGRES_USER: root
      POSTGRES_DB: root
    volumes:      
      - ' . $initDir . ':/docker-entrypoint-initdb.d:rw
    ports:
      - "' . $ports[$dbType] . ':5432"
';
				break;

			case 'mariadb':
				$docker_compose .= '    image: mariadb:' . $dbVersion . '
    restart: "always"
    environment:
      MYSQL_ROOT_PASSWORD: ' . $rootPassword . ' 
    volumes:
      - ' . $initDir . '.conf:/etc/mysql/conf.d:rw
      - ' . $initDir . ':/docker-entrypoint-initdb.d:rw
    ports:
      - "' . $ports[$dbType] . ':3306"
';
				break;

			case 'mysql':
				$docker_compose .= '    image: mysql:' . $dbVersion . '
    restart: "always"
    environment:
      MYSQL_ROOT_PASSWORD: ' . $rootPassword . '
    volumes:
      - ' . $initDir . '.conf:/etc/mysql/conf.d:rw
      - ' . $initDir . ':/docker-entrypoint-initdb.d:rw
    ports:
      - "' . $ports[$dbType] . ':3306"
';
				break;
		}

		$docker_compose .= "\n";
		$ports[$dbType]++;
	}
}

echo $docker_compose;
