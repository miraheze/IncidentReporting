<?php

class IncidentReportingHooks {

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-incidentreporting',
			'addTable',
			'incidents',
			__DIR__ . '/../sql/incidents.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-incidentreporting',
			'addTable',
			'incidents_log',
			__DIR__ . '/../sql/incidents_log.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-incidentreporting',
			'addTable',
			'incidents_reviewer',
			__DIR__ . '/../sql/incidents_reviewer.sql',
			true,
		] );
	}
}
