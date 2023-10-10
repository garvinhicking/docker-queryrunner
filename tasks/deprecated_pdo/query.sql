SELECT `report`.*
FROM `sys_http_report` `report`
         INNER JOIN (
    SELECT MAX(`tab_uuid`.`uuid`) AS `uuid`
    FROM `sys_http_report` `tab_uuid`
             INNER JOIN (
        SELECT MAX(`tab_summary`.`created`) AS `created`, summary
        FROM `sys_http_report` `tab_summary` GROUP BY `summary`
    ) `res_summary`
                        ON ((`tab_uuid`.`summary` = res_summary.summary) AND (`tab_uuid`.`created` = res_summary.created))
    GROUP BY `tab_uuid`.`summary`
) `res_uuid`
                    ON `report`.`uuid` = res_uuid.uuid
WHERE (`report`.`type` = 'csp-report') AND (`report`.`status` = 0)
ORDER BY `report`.`created` desc;
