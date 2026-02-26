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
$schemaName = ['tt_content', 'sys_category_record_mm'];
$tt_content = $schema->createTable('tt_content');
$tt_content->addColumn("uid", "integer", ["unsigned" => true, "autoincrement" => true]);
$tt_content->addColumn("pid", "integer", ["unsigned" => true]);
$tt_content->addColumn("bodytext", "text", ["length" => 16777215]);
$tt_content->addColumn("categories", "integer", ["unsigned" => true]);
$tt_content->addColumn("sorting", "integer", ["unsigned" => true, "default" => 0]);
$tt_content->setPrimaryKey(["uid"]);

$sys_category_record_mm = $schema->createTable('sys_category_record_mm');
$sys_category_record_mm->addColumn("sorting", "integer", ["unsigned" => true]);
$sys_category_record_mm->addColumn("fieldname", "text", ["length" => 64]);
$sys_category_record_mm->addColumn("tablenames", "text", ["length" => 64]);
$sys_category_record_mm->addColumn("uid_foreign", "integer", ["unsigned" => true]);
$sys_category_record_mm->addColumn("uid_local", "integer", ["unsigned" => true]);

$dropSchema = $schema->toDropSql($conn->getDatabasePlatform()); // get queries to safely delete this schema.
$addSchema = $schema->toSql($conn->getDatabasePlatform()); // get queries to create this schema.
