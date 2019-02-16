<?php
class IncidentReportingPager extends TablePager {
	private static $services = [];
	private static $causes = [];

	function __construct( $type, $component, $services ) {
		parent::__construct( $this->getContext() );
		$this->type = $type;
		$this->component = $component;

		$irServices = [];
		foreach ( $services as $service => $url ) {
			$niceName = str_replace( ' ', '-', strtolower( $service ) );
			$irServices[$niceName]['name'] = $service;

			if ( $url ) {
				$irServices[$niceName]['url'] = $url;
			}
		}

		self::$services = $irServices;

		self::$causes = [
			'human' => wfMessage( 'incidentreporting-label-human' )->text(),
			'technical' => wfMessage( 'incidentreporting-label-technical' )->text(),
			'upstream' =>  wfMessage( 'incidentreporting-label-upstream' )->text()
		];
	}

	function getFieldNames() {
		static $headers = null;

		$headers = [
			'i_id' => 'incidentreporting-table-id',
			'i_service' => 'incidentreporting-table-service',
			'i_cause' => 'incidentreporting-table-cause',
			'i_tasks' => 'incidentreporting-table-tasks',
			'i_published' => 'incidentreporting-table-published'
		];

		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'i_id':
				$formatted = Linker::makeExternalLink( SpecialPage::getTitleFor( 'IncidentReports' )->getFullURL() . '/' . $row->i_id, $row->i_id );
				break;
			case 'i_service':
				$service = $row->i_service;
				$formatted = ( self::$services[$service]['url'] ) ? Linker::makeExternalLink( self::$services[$service]['url'], self::$services[$service]['name'] ) : self::$services[$service]['name'];
				break;
			case 'i_cause':
				$formatted = self::$causes[$row->i_cause];
				break;
			case 'i_tasks':
				$taskArray = json_decode( $row->i_tasks, true );
				$formatted = count( $taskArray );
				break;
			case 'i_published':
				$formatted = wfTimestamp( TS_RFC2822, (int)$row->i_published );
				break;
			default:
				$formatted = "Unable to format $name";
				break;
		}
		return $formatted;
	}

	function getQueryInfo() {
		global $wgRottenLinksBadCodes;

		$info = [
			'tables' => [
				'incidents'
			],
			'fields' => [
				'i_id',
				'i_service',
				'i_cause',
				'i_published',
				'i_tasks'
			],
			'conds' => [
				'i_published IS NOT NULL'
			],
			'joins_conds' => [],
		];

		if ( $this->type ) {
			$info['conds']['i_cause'] = $this->type;
		}

		if ( $this->component ) {
			$info['conds']['i_service'] = $this->component;
		}

		return $info;
	}

	function getDefaultSort() {
		return 'i_id';
	}

	function isFieldSortable( $name ) {
		return true;
	}
}
