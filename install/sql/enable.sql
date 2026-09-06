SET @sHandlerName = 'my_una_world_lifecycle';
SET @iOldHandler = (SELECT `id` FROM `sys_alerts_handlers` WHERE `name` = @sHandlerName LIMIT 1);
DELETE FROM `sys_alerts` WHERE `handler_id` = @iOldHandler;
DELETE FROM `sys_alerts_handlers` WHERE `id` = @iOldHandler;
INSERT INTO `sys_alerts_handlers` (`name`, `class`, `file`, `service_call`) VALUES (@sHandlerName, 'MyUnaWorldAlertsResponse', 'modules/newton/my_una_world/classes/MyUnaWorldAlertsResponse.php', '');
SET @iHandler = LAST_INSERT_ID();
INSERT INTO `sys_alerts` (`unit`, `action`, `handler_id`) VALUES ('system', 'before_install', @iHandler), ('system', 'before_uninstall', @iHandler);
DELETE FROM `sys_cron_jobs` WHERE `name` = 'my_una_world_backup_worker';
INSERT INTO `sys_cron_jobs` (`name`, `time`, `class`, `file`, `service_call`) VALUES ('my_una_world_backup_worker', '* * * * *', 'MyUnaWorldCron', 'modules/newton/my_una_world/classes/MyUnaWorldCron.php', '');
