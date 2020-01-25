<?php
class SpecialIncidentReports extends SpecialPage {
	public function __construct() {
		parent::__construct( 'IncidentReports', 'viewincidents' );
	}

	public function execute( $par ) {
		global $wgIncidentReportingDatabase;

		$this->setHeaders();
		$this->checkPermissions();

		$par = explode( '/', $par );

		$dbw = wfGetDB( DB_MASTER, [], $wgIncidentReportingDatabase );

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
			$mwService = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();
			$edit = ( ( isset( $par[1] ) || (int)$par[0] == 0 ) && $mwService->userHasRight( $this->getContext()->getUser(), 'editincidents' ) );
			$this->showForm( (int)$par[0], $edit, $dbw, $isPublished );
		}
	}

	public function showForm(
		int $id,
		bool $edit,
		MaintainableDBConnRef $dbw,
		bool $isPublished
	) {
		$mwService = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$isPublished && !$mwService->userHasRight( $this->getContext()->getUser(), 'editincidents' ) ) {
			throw new PermissionsError( 'editincidents' );
		}

		$out = $this->getOutput();

		$out->addModules( 'ext.incidentreporting.oouiform' );

		$formFactory = new IncidentReportingFormFactory();
		$htmlForm = $formFactory->getForm( $id, $edit, $dbw, $this->getContext() );
		$sectionTitles = $htmlForm->getFormSections();

		$sectTabs = [];
		foreach( $sectionTitles as $key ) {
			$sectTabs[] = [
				'name' => $key,
				'label' => $htmlForm->getLegend( $key )
			];
		}

		$out->addJsConfigVars( 'wgIncidentReportingOOUIFormTabs', $sectTabs );

		$htmlForm->show();

	}

	public function showLanding( MaintainableDBConnRef $dbw ) {
		global $wgIncidentReportingServices;

		$type = $this->getRequest()->getText( 'type' );
		$component = $this->getRequest()->getText( 'component' );
//		$stats = $this->getRequest()->getText( 'stats' );
//		$selector = $this->getRequest()->getText( 'selector' );
//		$quantity = $this->getRequest()->getText( 'quantity' );

		$irServices = [
			wfMessage( 'incidentreporting-table-all' )->text() => ''
		];

		foreach ( $wgIncidentReportingServices as $service => $url ) {
		        $niceName = str_replace( ' ', '-', strtolower( $service ) );
		        $irServices[$service] = $niceName;
		}

		$formDescriptor = [
			'type' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-table-cause',
				'options' => [
					wfMessage( 'incidentreporting-label-human' )->text() => 'human',
					wfMessage( 'incidentreporting-label-technical' )->text() => 'technical',
					wfMessage( 'incidentreporting-label-upstream' )->text() => 'upstream',
					wfMessage( 'incidentreporting-table-all' )->text() => ''
				],
				'default' => '',
				'name' => 'type'
			],
			'component' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-table-service',
				'options' => $irServices,
				'default' => '',
				'name' => 'component'
			],
//			'statistics' => [
//				'type' => 'check',
//				'label-message' => 'incidentreporting-stats',
//				'default' => (bool)$stats,
//				'name' => 'stats'
//			],
//			'statistics-selector' => [
//				'type' => 'select',
//				'options' => [
//					wfMessage( 'incidentreporting-stats-type' )->text() => 'type',
//					wfMessage( 'incidentreporting-stats-component' )->text() => 'component',
//				],
//				'hide-if' => [ '!==', 'stats', '1' ],
//				'default' => $selector,
//				'name' => 'selector'
//			],
//			'statistics-quantity' => [
//				'type' => 'select',
//				'options' => [
//					wfMessage( 'incidentreporting-stats-number' )->text() => 'num',
//					wfMessage( 'incidentreporting-stats-visible' )->text() => 'visible',
//					wfMessage( 'incidentreporting-stats-total' )->text() => 'total'
//				],
//				'hide-if' => [ '!==', 'stats', '1' ],
//				'default' => $quantity,
//				'name' => $quantity
//			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( [ $this, 'dummyProcess' ] )->setMethod( 'get' )->prepareForm()->show();

//		$statsDescriptor = [];
//
//		$statsData = $dbw->select(
//			'incidents',
//			'*'
//		];
//
//		foreach ( $statsOut as $selector => $quantity ) {
//			$statDescriptor[] = [
//				'type' => 'info',
//				'label' => $selector,
//				'default' => $quantity
//			];
//		}

		$pager = new IncidentReportingPager( $type, $component, $wgIncidentReportingServices );
		$table = $pager->getBody();

		$this->getOutput()->addHTML( $pager->getNavigationBar() . $table . $pager->getNavigationBar() );

		$mwService = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();
		if ( $mwService->userHasRight( $this->getContext()->getUser(), 'editincidents' ) ) {
			$createForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitTextMsg( 'incidentreporting-create' )->setSubmitCallback( [ $this, 'onSubmitRedirectToCreate' ] ) ->prepareForm()->show();
		}

	}

	public static function onSubmitRedirectToCreate( $formData ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'IncidentReports' )->getFullUrl() . '/create' );

		return true;
	}

	public static function dummyProcess( $formData ) {
		return false;
	}
}
