<?php

class IncidentReportingHooks {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'incidents',
			__DIR__ . '/../sql/incidents.sql' );

		$updater->addExtensionTable( 'incidents_log',
			__DIR__ . '/../sql/incidents_log.sql' );

		$updater->addExtensionTable( 'incidents_reviewer',
			__DIR__ . '/../sql/incidents_reviewer.sql' );
	}
}
