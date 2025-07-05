<?php

namespace Miraheze\IncidentReporting;

use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\TablePager;
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

	public function formatValue( $field, $value ) {
		if ( $value === null ) {
			return '';
		}

		switch ( $field ) {
			case 'i_id':
				$formatted = $this->getLinkRenderer()->makeExternalLink( SpecialPage::getTitleFor( 'IncidentReports' )->getFullURL() . '/' . $value, $value, SpecialPage::getTitleFor( 'IncidentReports' ) );
				break;
			case 'i_service':
				$formatted = ( static::$services[$value]['url'] ) ? $this->getLinkRenderer()->makeExternalLink( static::$services[$value]['url'], static::$services[$value]['name'], SpecialPage::getTitleFor( 'IncidentReports' ) ) : htmlspecialchars( static::$services[$value]['name'], ENT_QUOTES );
				break;
			case 'i_cause':
				$formatted = static::$causes[$value];
				break;
			case 'i_tasks':
				$taskArray = json_decode( $value, true );
				$formatted = is_array( $taskArray ) ? count( $taskArray ) : 0;
				break;
			case 'i_published':
				$formatted = wfTimestamp( TS_RFC2822, (int)$value );
				break;
			default:
				$formatted = "Unable to format $field";
		}

		return $formatted;
	}

	public function getQueryInfo() {
		$info = [
			'tables' => [
				'incidents',
			],
			'fields' => [
				'i_id',
				'i_service',
				'i_cause',
				'i_published',
				'i_tasks',
			],
			'conds' => [
				'i_published IS NOT NULL',
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

	public function isFieldSortable( $field ) {
		return $field !== 'i_tasks';
	}
}
