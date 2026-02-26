<?php
// $conn comes from outside, this file gets included.
if (!isset($conn)) {
    die('This file is not meant to be called directly, it is just included.');
}

// Passes back $queryResult
$queryResult = '';

/** @var \Doctrine\DBAL\Connection $conn */
$query = $conn->createQueryBuilder()
    ->select('COUNT(*) AS counter')
    ->from('sys_http_report')
    ->orderBy('uuid', 'DESC')
    ;

$queryResult = $query->executeQuery()->fetchAllAssociative();
