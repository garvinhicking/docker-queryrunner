<?php
// $conn comes from outside, this file gets included.
if (!isset($conn)) {
    die('This file is not meant to be called directly, it is just included.');
}

// Passes back $queryResult
$queryResult = '';

/** @var \Doctrine\DBAL\Connection $conn */
$query = $conn->createQueryBuilder()
    ->select('tt_content.*')
    ->from('tt_content', 'tt_content')
    ->join('tt_content', 'sys_category_record_mm', 'sys_category_record_mm', 'uid = sys_category_record_mm.uid_foreign AND sys_category_record_mm.uid_local IN (1,2,3,4)')
    ->where("tablenames = 'tt_content' AND fieldname = 'categories'")
    ->orderBy('tt_content.sorting', 'DESC')
    ->groupBy('tt_content.uid');

try {
    $queryResult = $query->executeQuery()->fetchAllAssociative();
} catch (Throwable $e) {
    die($e->getMessage());
}
