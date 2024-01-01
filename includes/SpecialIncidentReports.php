<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use Wikimedia\Rdbms\DBConnRef;

class SpecialIncidentReports extends SpecialPage {
	/** @var Config */
	private $config;

	/** @var PermissionManager */
	private $permissionManager;

	public function __construct() {
		parent::__construct( 'IncidentReports', 'viewincidents' );
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'incidentreporting' );
		$this->permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();

		$par = explode( '/', $par );

		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $this->config->get( 'IncidentReportingDatabase' ) )
			->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->config->get( 'IncidentReportingDatabase' ) );

		$inc = $dbw->selectRow(
			'incidents',
			'*',
			[
				'i_id' => (int)$par[0]
			]
		);

		$isPublished = ( $inc ) ? (bool)$inc->i_published : false;

		if ( $par[0] == '' || ( (int)$par[0] != 0 && !$inc ) ) {
			$this->showLanding( $dbw );
		} else {
			$edit = ( ( isset( $par[1] ) || (int)$par[0] == 0 ) && $this->permissionManager->userHasRight( $this->getContext()->getUser(), 'editincidents' ) );
			$this->showForm( (int)$par[0], $edit, $dbw, $isPublished );
		}
	}

	public function showForm(
		int $id,
		bool $edit,
		DBConnRef $dbw,
		bool $isPublished
	) {
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
		$htmlForm = $formFactory->getForm( $id, $edit, $dbw, $this->getContext() );

		$htmlForm->show();
	}

	public function showLanding( DBConnRef $dbw ) {
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

		foreach ( $servicesConfig as $service => $url ) {
			$niceName = str_replace( ' ', '-', strtolower( $service ) );
			$irServices[$service] = $niceName;
		}

		$showAll = [ $this->msg( 'incidentreporting-table-all' )->text() => '' ];

		$formDescriptor = [
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
						$statsData = $dbw->selectFieldValues(
							'incidents',
							$field, [
								$where => $key,
								'i_published >= ' . ( $published == '' ? '0' : $dbw->timestamp( wfTimestamp( TS_RFC2822, "{$published}T00:00:00.000Z" ) ) )
							]
						);

						$minutes = $this->msg( 'incidentreporting-label-outage-formatted', array_sum( $statsData ) )->text();

						$formDescriptor += [
							"statistics-out-quantity-{$key}" => [
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
					$statsData = $dbw->selectFieldValues(
						'incidents',
						$field, [
							$where => $key,
							'i_published >= ' . ( $published == '' ? '0' : $dbw->timestamp( wfTimestamp( TS_RFC2822, "{$published}T00:00:00.000Z" ) ) )
						]
					);

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
		$htmlForm->setMethod( 'get' )->prepareForm()->displayForm( false );

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );

		if ( $this->permissionManager->userHasRight( $this->getContext()->getUser(), 'editincidents' ) ) {
			$createForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitTextMsg( 'incidentreporting-create' )->setSubmitCallback( [ $this, 'onSubmitRedirectToCreate' ] )->prepareForm()->show();
		}
	}

	public static function onSubmitRedirectToCreate( $formData ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'IncidentReports' )->getFullURL() . '/create' );

		return true;
	}
}
