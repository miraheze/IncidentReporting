CREATE TABLE /*_*/incidents_log (
  `log_incident` INT UNSIGNED NOT NULL,
  `log_id` INT UNSIGNED NOT NULL,
  `log_actor` TEXT NOT NULL,
  `log_action` LONGTEXT NOT NULL,
  `log_timestamp` BINARY(14) NOT NULL,
  `log_state` TEXT NOT NULL
) /*$wgDBTableOptions*/;

