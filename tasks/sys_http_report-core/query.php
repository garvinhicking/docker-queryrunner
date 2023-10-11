<?php
// $conn comes from outside, this file gets included.
if (!isset($conn)) {
    die('This file is not meant to be called directly, it is just included.');
}

if (!function_exists('createFunctionLiteral')) {
    function createFunctionLiteral(\Doctrine\DBAL\Query\QueryBuilder &$queryBuilder, string $functionName, string $fieldName, string $alias = null): string
    {
        global $conn;

        $values = [
            $functionName,
            $conn->quoteIdentifier($fieldName),
        ];
        if ($alias === null) {
            $format = '%s(%s)';
        } else {
            $format = '%s(%s) AS %s';
            $values[] = $conn->quoteIdentifier($alias);
        }
        return vsprintf($format, $values);
    }

    function applySummaryJoin(\Doctrine\DBAL\Query\QueryBuilder &$queryBuilder, string $fromAlias, string $join, string $alias, string $condition): void
    {
        global $conn;

        $queryBuilder->join(
            $fromAlias,
            sprintf('(%s)', $join),
            $alias,
            $condition
        );
    }

}

// Passes back $queryResult
$queryResult = '';

/** @var \Doctrine\DBAL\Connection $conn */

$TABLE_NAME = 'sys_http_report';
$queryBuilder = $conn->createQueryBuilder();
$queryBuilder->from($TABLE_NAME, 'report');

$uuidQueryBuilder = $conn->createQueryBuilder()->from($TABLE_NAME, 'tab_uuid');
$summaryQueryBuilder = $conn->createQueryBuilder()->from($TABLE_NAME, 'tab_summary');
$expr = $queryBuilder->expr();

$summaryQueryBuilder
    ->select(createFunctionLiteral(
        $queryBuilder,
        'MAX',
        'tab_summary.created',
        'created'
    ))
    ->addSelect('summary')
    ->groupBy('summary');

applySummaryJoin(
    $uuidQueryBuilder,
    'tab_uuid',
    $summaryQueryBuilder->getSQL(),
    'res_summary',
    (string)$expr->and(
        $expr->eq('tab_uuid.summary', 'res_summary.summary'),
        $expr->eq('tab_uuid.created', 'res_summary.created')
    )
);
$uuidQueryBuilder
    ->select(createFunctionLiteral(
        $queryBuilder,
        // using MAX(col) since ANY_VALUE(col) is not supported by PostgreSQL
        'MAX',
        'tab_uuid.uuid',
        'uuid'
    ))
    ->groupBy('tab_uuid.summary');

applySummaryJoin(
    $queryBuilder,
    'report',
    $uuidQueryBuilder->getSQL(),
    'res_uuid',
    $expr->eq('report.uuid', 'res_uuid.uuid')
);

$_queryResult = $queryBuilder
    ->select('report.*')
    ->orderBy('report.created', 'desc')
    ->andWhere(
        (string)$expr->and(
            $expr->eq('report.status', '0'),
            $expr->eq('report.type', "'csp-report'")
        )
    );

echo $_queryResult->getSQL();

$queryResult = $_queryResult->executeQuery()->fetchAllAssociative();
