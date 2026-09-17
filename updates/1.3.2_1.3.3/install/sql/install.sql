SET @iHandler = (SELECT `id` FROM `sys_alerts_handlers` WHERE `name` = 'my_una_world_lifecycle' LIMIT 1);

INSERT INTO `sys_alerts` (`unit`, `action`, `handler_id`) SELECT 'system', 'design_included', @iHandler WHERE @iHandler IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `sys_alerts` WHERE `unit` = 'system' AND `action` = 'design_included' AND `handler_id` = @iHandler);
