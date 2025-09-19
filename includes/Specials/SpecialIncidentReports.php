<?php

namespace Miraheze\IncidentReporting\Specials;

use MediaWiki\Config\Config;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\IncidentReporting\IncidentReportingFormFactory;
use Miraheze\IncidentReporting\IncidentReportingPager;
use PermissionsError;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

class SpecialIncidentReports extends SpecialPage {
	/** @var Config */
	private $config;

	/** @var PermissionManager */
	private $permissionManager;

	public function __construct() {
		parent::__construct( 'IncidentReports', 'viewincidents' );
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'IncidentReporting' );
		$this->permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();

		$par = explode( '/', $par );

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()
			->getReplicaDatabase( 'virtual-incidentreporting' );

		$inc = $dbr->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'incidents' )
			->where( [ 'i_id' => (int)$par[0] ] )
			->caller( __METHOD__ )
			->fetchRow();

		$isPublished = ( $inc ) ? (bool)$inc->i_published : false;

		if ( $par[0] == '' || ( (int)$par[0] != 0 && !$inc ) ) {
			$this->showLanding( $dbr );
		} else {
			$edit = ( ( isset( $par[1] ) || (int)$par[0] == 0 ) && $this->permissionManager->userHasRight( $this->getContext()->getUser(), 'editincidents' ) );
			$this->showForm( (int)$par[0], $edit, $isPublished );
		}
	}

	public function showForm( int $id, bool $edit, bool $isPublished ) {
		if ( !$isPublished && !$this->permissionManager->userHasRight( $this->getContext()->getUser(), 'editincidents' ) ) {
			throw new PermissionsError( 'editincidents' );
		}

		$out = $this->getOutput();

		$out->addModules( [ 'ext.incidentreporting.oouiform' ] );

		$out->addModuleStyles( [
			'ext.incidentreporting.oouiform.styles',
			'mediawiki.widgets.TagMultiselectWidget.styles',
		] );

		$out->addModuleStyles( [ 'oojs-ui-widgets.styles' ] );

		$formFactory = new IncidentReportingFormFactory();
		$htmlForm = $formFactory->getForm( $id, $edit, $this->getContext() );

		$htmlForm->show();
	}

	public function showLanding( IReadableDatabase $dbr ) {
		$type = $this->getRequest()->getText( 'type' );
		$component = $this->getRequest()->getText( 'component' );
		$published = $this->getRequest()->getText( 'published' );
		$stats = $this->getRequest()->getText( 'stats' );
		$selector = $this->getRequest()->getText( 'selector' );
		$quantity = $this->getRequest()->getText( 'quantity' );

		$types = [
			$this->msg( 'incidentreporting-label-human' )->text() => 'human',
			$this->msg( 'incidentreporting-label-technical' )->text() => 'technical',
			$this->msg( 'incidentreporting-label-upstream' )->text() => 'upstream',
		];

		$irServices = [];

		$servicesConfig = array_merge(
			$this->config->get( 'IncidentReportingServices' ),
			$this->config->get( 'IncidentReportingInactiveServices' )
		);

		ksort( $servicesConfig );

		foreach ( $servicesConfig as $service => $_ ) {
			$niceName = str_replace( ' ', '-', strtolower( $service ) );
			$irServices[$service] = $niceName;
		}

		$showAll = [ $this->msg( 'incidentreporting-table-all' )->text() => '' ];

		$formDescriptor = [
			'info' => [
				'type' => 'info',
				'default' => $this->msg( 'incidentreporting-header-info' )->text(),
			],
			'type' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-table-cause',
				'options' => $types + $showAll,
				'default' => '',
				'name' => 'type'
			],
			'component' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-table-service',
				'options' => $irServices + $showAll,
				'default' => '',
				'name' => 'component'
			],
			'statistics' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-stats',
				'default' => (bool)$stats,
				'name' => 'stats'
			],
			'statistics-selector' => [
				'type' => 'select',
				'options' => [
					$this->msg( 'incidentreporting-stats-type' )->text() => 'type',
					$this->msg( 'incidentreporting-stats-component' )->text() => 'component',
				],
				'hide-if' => [ '!==', 'statistics', '1' ],
				'default' => $selector,
				'name' => 'selector'
			],
			'statistics-quantity' => [
				'type' => 'select',
				'options' => [
					$this->msg( 'incidentreporting-stats-number' )->text() => 'num',
					$this->msg( 'incidentreporting-stats-visible' )->text() => 'visible',
					$this->msg( 'incidentreporting-stats-total' )->text() => 'total'
				],
				'hide-if' => [ '!==', 'statistics', '1' ],
				'default' => $quantity,
				'name' => 'quantity'
			],
			'statistics-published' => [
				'type' => 'date',
				'label-message' => 'incidentreporting-stats-published',
				'hide-if' => [ '!==', 'statistics', '1' ],
				'default' => $published,
				'name' => 'published'
			]
		];

		$pager = new IncidentReportingPager( $type, $component, $servicesConfig );

		switch ( $quantity ) {
			case 'num':
				$field = 'i_id';
				break;
			case 'visible':
				$field = 'i_outage_visible';
				break;
			case 'total':
				$field = 'i_outage_total';
				break;
			default:
				$field = false;
		}

		$foreach = [];

		$where = false;
		$all = false;

		if ( $selector === 'type' ) {
			$where = 'i_cause';
			$foreach = $types;
			$all = ( $type === '' );
		} elseif ( $selector === 'component' ) {
			$where = 'i_service';
			$foreach = $irServices;
			$all = ( $component === '' );
		}

		if ( $field && $where ) {
			if ( $all ) {
				foreach ( $foreach as $label => $key ) {
						$statsData = $dbr->newSelectQueryBuilder()
							->select( $field )
							->from( 'incidents' )
							->where( [
								$where => $key,
								$dbr->expr( 'i_published', '>=', ( $published === '' ? '0' : $dbr->timestamp( wfTimestamp( TS_RFC2822, "{$published}T00:00:00.000Z" ) ) ) ),
							] )
							->caller( __METHOD__ )
							->fetchFieldValues();

						$minutes = $this->msg( 'incidentreporting-label-outage-formatted', array_sum( $statsData ) )->text();

						$formDescriptor += [
							"statistics-out-quantity-$key" => [
								'type' => 'info',
								'label' => $label,
								'default' => $quantity === 'num' ? (string)count( $statsData ) : $minutes,
							]
						];
				}
			} else {
				$key = '';
				if ( $selector === 'type' ) {
					$key = $type;
				} elseif ( $selector === 'component' ) {
					$key = $component;
				}

				if ( in_array( $key, $foreach ) ) {
					$statsData = $dbr->newSelectQueryBuilder()
						->select( $field )
						->from( 'incidents' )
						->where( [
							$where => $key,
							$dbr->expr( 'i_published', '>=', ( $published === '' ? '0' : $dbr->timestamp( wfTimestamp( TS_RFC2822, "{$published}T00:00:00.000Z" ) ) ) ),
						] )
						->caller( __METHOD__ )
						->fetchFieldValues();

					$label = array_flip( $foreach )[$key];
					$minutes = $this->msg( 'incidentreporting-label-outage-formatted', array_sum( $statsData ) )->text();

					$formDescriptor += [
						"statistics-out-quantity-{$key}" => [
							'type' => 'info',
							'label' => $label,
							'default' => $quantity === 'num' ? (string)count( $statsData ) : $minutes,
						],
					];
				}
			}
		}

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'incidentreporting-header' )
			->setSubmitTextMsg( 'search' )
			->prepareForm()
			->displayForm( false );

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );

		if ( $this->permissionManager->userHasRight( $this->getContext()->getUser(), 'editincidents' ) ) {
			$createForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitTextMsg( 'incidentreporting-create' )->setSubmitCallback( [ $this, 'onSubmitRedirectToCreate' ] )->prepareForm()->show();
		}
	}

	public function onSubmitRedirectToCreate(): void {
		$this->getOutput()->redirect(
			$this->getPageTitle( 'create' )->getFullURL()
		);
	}
}
