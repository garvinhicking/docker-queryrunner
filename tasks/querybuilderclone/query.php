<?php
// $conn comes from outside, this file gets included.
if (!isset($conn)) {
    die('This file is not meant to be called directly, it is just included.');
}

// Passes back $queryResult
$queryResult = '';

/** @var \Doctrine\DBAL\Connection $conn */
$query = $conn->createQueryBuilder()
    ->select('r.uuid')
    ->from('sys_http_report', 'r');

$query = $query->where(
    $query->expr()->and(
        $query->expr()->eq('r.status', '0'),
        $query->expr()->eq('r.status', $query->createNamedParameter('0', \Doctrine\DBAL\ParameterType::INTEGER))
    )
);

$first = $conn->createQueryBuilder()
    ->select('r.uuid')
    ->from('sys_http_report', 'r');

$first = $first->where(
    $first->expr()->and(
        $first->expr()->eq('r.status', '0'),
        $first->expr()->eq('r.status', $first->createNamedParameter('0', \Doctrine\DBAL\ParameterType::INTEGER))
    )
);

echo "SQL: " . $query->getSQL() . "\n";
print_r($query->getParameters());

$second = $conn->createQueryBuilder()
    ->select('r2.*')
    ->from('sys_http_report', 'r2');

$second = $second->where(
    $second->expr()->and(
        $second->expr()->lt('r2.status', $second->createNamedParameter('1', \Doctrine\DBAL\ParameterType::INTEGER)),
        $second->expr()->lt('r2.status', $second->createNamedParameter('1', \Doctrine\DBAL\ParameterType::INTEGER)),
        $second->expr()->gte('r2.created', $second->createNamedParameter('1', \Doctrine\DBAL\ParameterType::INTEGER)),
        $second->expr()->lte('r2.created', $second->createNamedParameter('999', \Doctrine\DBAL\ParameterType::INTEGER)),
    )
);
$second = $second->andWhere($query->getSQL());

echo "SQL2: " . $second->getSQL() . "\n";
print_r($second->getParameters());

$third = $conn->createQueryBuilder()
    ->select('r2.*')
    ->from('sys_http_report', 'r2');

$third = $third->where(
    $third->expr()->and(
        $third->expr()->lt('r2.status', $first->createNamedParameter('1', \Doctrine\DBAL\ParameterType::INTEGER)),
        $third->expr()->lt('r2.status', $first->createNamedParameter('1', \Doctrine\DBAL\ParameterType::INTEGER)),
        $third->expr()->gte('r2.created', $first->createNamedParameter('1', \Doctrine\DBAL\ParameterType::INTEGER)),
        $third->expr()->lte('r2.created', $first->createNamedParameter('999', \Doctrine\DBAL\ParameterType::INTEGER)),
    )
);
$third = $third->andWhere($third->getSQL());

echo "SQL3.1: " . $first->getSQL() . "\n";
echo "SQL3.2: " . $third->getSQL() . "\n";
print_r($first->getParameters());
print_r($third->getParameters());

$queryResult = $second->executeQuery()->fetchAllAssociative();
print_r($queryResult);
exit;

$queryResult = $query->executeQuery()->fetchAllAssociative();
