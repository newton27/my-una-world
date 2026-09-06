SET @sHandlerName = 'my_una_world_lifecycle';
SET @iHandler = (SELECT `id` FROM `sys_alerts_handlers` WHERE `name` = @sHandlerName LIMIT 1);
DELETE FROM `sys_alerts` WHERE `handler_id` = @iHandler;
DELETE FROM `sys_alerts_handlers` WHERE `id` = @iHandler;
DELETE FROM `sys_cron_jobs` WHERE `name` = 'my_una_world_backup_worker';
