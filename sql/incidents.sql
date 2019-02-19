CREATE TABLE /*_*/incidents (
  `i_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `i_service` TEXT NOT NULL,
  `i_cause` TEXT NOT NULL,
  `i_aggravation` LONGTEXT DEFAULT NULL,
  `i_known` LONGTEXT DEFAULT NULL,
  `i_preventable` LONGTEXT DEFAULT NULL,
  `i_other` LONGTEXT DEFAULT NULL,
  `i_responders` TEXT DEFAULT NULL,
  `i_published` BINARY(14) DEFAULT NULL,
  `i_tasks` TEXT DEFAULT NULL,
  `i_outage_visible` BINARY(14) DEFAULT NULL,
  `i_outage_total` BINARY(14) DEFAULT NULL
) /*$wgDBTableOptions*/;

