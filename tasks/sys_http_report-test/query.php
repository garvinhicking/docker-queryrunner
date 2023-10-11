<?php
// $conn comes from outside, this file gets included.
if (!isset($conn)) {
    die('This file is not meant to be called directly, it is just included.');
}

// Passes back $queryResult
$queryResult = '';

/** @var \Doctrine\DBAL\Connection $conn */
$query = $conn->createQueryBuilder()
    ->select('MAX(r.created)', 'r.summary', 'r2.uuid')
    ->from('sys_http_report', 'r')
    ->join('r', 'sys_http_report', 'r2', 'r2.summary = r.summary')
    ->groupBy('r.summary');

echo "SQL: " . $query->getSQL() . "\n";

$queryResult = $query->executeQuery()->fetchAllAssociative();
