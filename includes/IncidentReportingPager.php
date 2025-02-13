<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

class IncidentReportingPager extends TablePager {
	/** @var array */
	private static $services;

	/** @var array */
	private static $causes;

	/** @var string */
	private $component;

	/** @var string */
	private $type;

	public function __construct( $type, $component, $services ) {
		parent::__construct( $this->getContext() );
		$this->type = $type;
		$this->component = $component;

		$this->mDb = MediaWikiServices::getInstance()->getConnectionProvider()
			->getReplicaDatabase( 'virtual-incidentreporting' );

		$irServices = [];
		foreach ( $services as $service => $url ) {
			$niceName = str_replace( ' ', '-', strtolower( $service ) );
			$irServices[$niceName]['name'] = $service;
			$irServices[$niceName]['url'] = $url;
		}

		static::$services = $irServices;

		static::$causes = [
			'human' => $this->msg( 'incidentreporting-label-human' )->escaped(),
			'technical' => $this->msg( 'incidentreporting-label-technical' )->escaped(),
			'upstream' => $this->msg( 'incidentreporting-label-upstream' )->escaped()
		];
	}

	public function getFieldNames() {
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

	public function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'i_id':
				$formatted = $this->getLinkRenderer()->makeExternalLink( SpecialPage::getTitleFor( 'IncidentReports' )->getFullURL() . '/' . $row->i_id, $row->i_id, SpecialPage::getTitleFor( 'IncidentReports' ) );
				break;
			case 'i_service':
				$service = $row->i_service;
				$formatted = ( static::$services[$service]['url'] ) ? $this->getLinkRenderer()->makeExternalLink( static::$services[$service]['url'], static::$services[$service]['name'], SpecialPage::getTitleFor( 'IncidentReports' ) ) : htmlspecialchars( static::$services[$service]['name'], ENT_QUOTES );
				break;
			case 'i_cause':
				$formatted = static::$causes[$row->i_cause];
				break;
			case 'i_tasks':
				$taskArray = json_decode( $row->i_tasks, true );
				$formatted = is_array( $taskArray ) ? count( $taskArray ) : 0;
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

	public function getQueryInfo() {
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

	public function getDefaultSort() {
		return 'i_id';
	}

	public function isFieldSortable( $name ) {
		return true;
	}
}
