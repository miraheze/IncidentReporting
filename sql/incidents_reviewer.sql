CREATE TABLE /*_*/incidents_reviewer (
  `r_incident` INT UNSIGNED NOT NULL,
  `r_user` TEXT NOT NULL,
  `r_timestamp` BINARY(14) DEFAULT NULL
) /*$wgDBTableOptions*/;

