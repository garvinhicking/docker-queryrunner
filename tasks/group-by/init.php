<?php
// $conn comes from outside, this file gets included.
if (!isset($conn)) {
    die('This file is not meant to be called directly, it is just included.');
}

// These variables are reused outside!
$dropSchema = $addSchema = $schemaName = null;

/** @todo
 * There should be a way to somehow import a MYSQL SQL dump and use that as a common ground?
 */

/** @var \Doctrine\DBAL\Connection $conn */
$schema = new \Doctrine\DBAL\Schema\Schema();
$schemaName = 'sys_http_report';
$myTable = $schema->createTable($schemaName);
$myTable->addColumn("uuid", "string", ["length" => 36]);
$myTable->addColumn("status", "smallint", ["unsigned" => true]);
$myTable->addColumn("created", "integer", ["unsigned" => true]);
$myTable->addColumn("changed", "integer", ["unsigned" => true]);
$myTable->addColumn("type", "string", ["length" => 32]);
$myTable->addColumn("scope", "string", ["length" => 32]);
$myTable->addColumn("request_time", "bigint", ["unsigned" => true]);
$myTable->addColumn("meta", "text", ["length" => 16777215]);
$myTable->addColumn("details", "text", ["length" => 16777215]);
$myTable->addColumn("summary", "string", ["length" => 40]);

$myTable->setPrimaryKey(["uuid"]);

$myTable->addIndex(['type', 'scope']);
$myTable->addIndex(['created']);
$myTable->addIndex(['changed']);
$myTable->addIndex(['request_time']);

$dropSchema = $schema->toDropSql($conn->getDatabasePlatform()); // get queries to safely delete this schema.
$addSchema = $schema->toSql($conn->getDatabasePlatform()); // get queries to create this schema.

