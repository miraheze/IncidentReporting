<?php

namespace Miraheze\IncidentReporting;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;
use Wikimedia\Rdbms\SelectQueryBuilder;

class IncidentReportingFormFactory {
	/** @var Config */
	private $config;

	/** @var PermissionManager */
	private $permissionManager;

	public function __construct() {
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'IncidentReporting' );
		$this->permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
	}

	public function getFormDescriptor(
		int $id,
		bool $edit,
		IContextSource $context,
		IDatabase $dbw
	) {
		$context->getOutput()->enableOOUI();

		if ( !$id ) {
			$action = 'create';
		} elseif ( $edit ) {
			$action = 'edit';
		} else {
			$action = 'view';
		}

		if ( $action == 'create' ) {
			$data = null;
		} else {
			$data = $dbw->newSelectQueryBuilder()
				->select( ISQLPlatform::ALL_ROWS )
				->from( 'incidents' )
				->where( [ 'i_id' => $id ] )
				->caller( __METHOD__ )
				->fetchRow();
		}

		$irServices = [];
		$irServicesUrl = [];

		$servicesConfig = array_merge(
			$this->config->get( 'IncidentReportingServices' ),
			$this->config->get( 'IncidentReportingInactiveServices' )
		);

		ksort( $servicesConfig );

		foreach ( $servicesConfig as $service => $url ) {
			$niceName = str_replace( ' ', '-', strtolower( $service ) );
			$irServices[$service] = $niceName;

			if ( $url ) {
				$irServicesUrl[$niceName] = $url;
			}
		}

		$userFactory = MediaWikiServices::getInstance()->getUserFactory();

		$revServices = array_flip( $irServices );

		$responders = [];

		$userLinkRenderer = MediaWikiServices::getInstance()->getUserLinkRenderer();

		if ( $data !== null ) {
			$respArray = explode( "\n", $data->i_responders );

			if ( count( $respArray ) != 0 ) {
				foreach ( $respArray as $resp ) {
					$user = $userFactory->newFromName( $resp );
					if ( $user ) {
						$responders[] = $userLinkRenderer->userLink( $user, $context );
					}
				}
			}
		}

		$reviewers = [
			'reviewed' => [],
			'unreviewed' => [],
			'all' => []
		];

		if ( $id ) {
			$dbReviewers = $dbw->newSelectQueryBuilder()
				->select( ISQLPlatform::ALL_ROWS )
				->from( 'incidents_reviewer' )
				->where( [ 'r_incident' => $id ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $dbReviewers as $db ) {
				$user = $userFactory->newFromName( $db->r_user );
				if ( $user ) {
					if ( $db->r_timestamp ) {
						$reviewers['reviewed'][] = $userLinkRenderer->userLink( $user, $context );
					} else {
						$reviewers['unreviewed'][] = $userLinkRenderer->userLink( $user, $context );
					}
				}

				$reviewers['all'][] = $db->r_user;
			}

			$reviewers['reviewed'] = ( count( $reviewers['reviewed'] ) != 0 ) ? implode( ', ', $reviewers['reviewed'] ) : 'None';
			$reviewers['unreviewed'] = ( count( $reviewers['unreviewed'] ) != 0 ) ? implode( ', ', $reviewers['unreviewed'] ) : 'None';

		}

		$buildDescriptor = [
			'service' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-label-service',
				'options' => array_diff_key( $irServices, $this->config->get( 'IncidentReportingInactiveServices' ) ),
				'default' => ( $data !== null ) ? $data->i_service : '',
				'section' => 'main'
			],
			'cause' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-label-cause',
				'options' => [
					wfMessage( 'incidentreporting-label-human' )->text() => 'human',
					wfMessage( 'incidentreporting-label-technical' )->text() => 'technical',
					wfMessage( 'incidentreporting-label-upstream' )->text() => 'upstream'
				],
				'section' => 'main',
				'default' => ( $data !== null ) ? $data->i_cause : 'human'
			],
			'control-aggravation' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-aggravation',
				'section' => 'main',
				'default' => ( $data !== null ) ? (bool)$data->i_aggravation : false
			],
			'aggravation' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( $data !== null ) ? $data->i_aggravation : '',
				'hide-if' => [ '!==', 'control-aggravation', '1' ]
			],
			'control-known' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-known',
				'section' => 'main',
				'default' => ( $data !== null ) ? (bool)$data->i_known : false
			],
			'known' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( $data !== null ) ? $data->i_known : '',
				'hide-if' => [ '!==', 'control-known', '1' ]
			],
			'control-preventable' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-preventable',
				'section' => 'main',
				'default' => ( $data !== null ) ? (bool)$data->i_preventable : false
			],
			'preventable' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( $data !== null ) ? $data->i_preventable : '',
				'hide-if' => [ '!==', 'control-preventable', '1' ]
			],
			'control-other' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-other',
				'section' => 'main',
				'default' => ( $data !== null ) ? (bool)$data->i_other : false
			],
			'other' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( $data !== null ) ? $data->i_other : '',
				'hide-if' => [ '!==', 'control-other', '1' ]
			],
			'responders' => [
				'type' => 'usersmultiselect',
				'label-message' => 'incidentreporting-label-responders',
				'section' => 'main',
				'default' => ( $data !== null ) ? $data->i_responders : '',
				'required' => true,
				'exitsts' => true
			],
			'review' => [
				'type' => 'usersmultiselect',
				'label-message' => 'incidentreporting-label-reviewers',
				'section' => 'main',
				'default' => implode( "\n", $reviewers['all'] ),
				'required' => true,
				'exists' => true
			],
		];

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$viewDescriptor = [
			'service' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-service',
				'default' => ( isset( $irServicesUrl[$data->i_service] ) ) ? $linkRenderer->makeExternalLink( $irServicesUrl[$data->i_service], $revServices[$data->i_service], SpecialPage::getTitleFor( 'IncidentReports' ) ) : htmlspecialchars( $revServices[$data->i_service], ENT_QUOTES ),
				'raw' => true,
				'section' => 'main'
			],
			'outage-visible' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-outage-visible',
				'section' => 'main',
				'default' => wfMessage( 'incidentreporting-label-outage-formatted', $data->i_outage_visible )->text()
			],
			'outage-total' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-outage-total',
				'section' => 'main',
				'default' => wfMessage( 'incidentreporting-label-outage-formatted', $data->i_outage_total )->text()
			],
			'cause' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-cause',
				'section' => 'main',
				'default' => wfMessage( "incidentreporting-label-{$data->i_cause}" )->text(),
			],
			'aggravation' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-aggravation',
				'section' => 'main',
				'default' => $data->i_aggravation ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'known' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-known',
				'section' => 'main',
				'default' => $data->i_known ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'preventable' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-preventable',
				'section' => 'main',
				'default' => $data->i_preventable ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'other' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-other',
				'section' => 'main',
				'default' => $data->i_other ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'responders' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-responders',
				'section' => 'main',
				'raw' => true,
				'default' => implode( "\n", $responders )
			],
			'review' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-reviewers',
				'section' => 'main',
				'default' => wfMessage( 'incidentreporting-label-reviewers-info' )->rawParams( $reviewers['reviewed'], $reviewers['unreviewed'] )->parse(),
				'raw' => true
			],
			'published' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-published',
				'section' => 'main',
				'default' => ( $data->i_published !== null ) ? wfTimestamp( TS_RFC2822, (int)$data->i_published ) : wfMessage( 'incidentreporting-label-notpublished' )->text()
			]
		];

		if ( $data === null || $data->i_published === null ) {
			$buildDescriptor['publish'] = [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-publish',
				'section' => 'main',
				'default' => ( $data !== null ) ? (bool)$data->i_published : false,
				'disabled' => !$edit
			];
		}

		// build a log like above
		$buildLog = [];
		$logData = $dbw->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'incidents_log' )
			->where( [ 'log_incident' => $id ] )
			->orderBy( 'log_timestamp', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $action == 'view' ) {
			if ( $logData->numRows() ) {
				foreach ( $logData as $ldata ) {
					$buildLog[$ldata->log_id] = [
						'type' => 'info',
						'label' => wfMessage( 'incidentreporting-log-header', $ldata->log_actor, wfTimestamp( TS_RFC2822, (int)$ldata->log_timestamp ), $ldata->log_state )->text(),
						'section' => 'logs',
						'subsection' => (string)$ldata->log_id,
						'raw' => true,
						'default' => $context->getOutput()->parseAsInterface( $ldata->log_action )
					];
				}
			} else {
				$buildLog[] = [
					'type' => 'info',
					'label-message' => 'incidentreporting-log-no-data'
				];
			}
		} else {
			$logId = 0;

			if ( $action == 'edit' && $logData->numRows() ) {
				foreach ( $logData as $ldata ) {
					$logId = (int)$ldata->log_id;

					$buildLog["{$logId}-timestamp"] = [
						'type' => 'datetime',
						'label' => wfMessage( 'incidentreporting-log-timestamp', $logId )->text(),
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => wfTimestamp( TS_ISO_8601, (int)$ldata->log_timestamp )
					];

					$buildLog["{$logId}-actor"] = [
						'type' => 'select',
						'label' => wfMessage( 'incidentreporting-log-actor', $logId )->text(),
						'options' => [
							wfMessage( 'incidentreporting-log-actor-information' )->text() => 'information',
							wfMessage( 'incidentreporting-log-actor-user' )->text() => 'user'
						],
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => ( $ldata->log_actor == 'information' ) ? 'information' : 'user'
					];

					$buildLog["{$logId}-user"] = [
						'type' => 'user',
						'label' => wfMessage( 'incidentreporting-log-user', $logId )->text(),
						'exists' => true,
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => $ldata->log_actor,
						'hide-if' => [ '!==', "{$logId}-actor", 'user' ]
					];
					$buildLog["{$logId}-action"] = [
						'type' => 'text',
						'label' => wfMessage( 'incidentreporting-log-action', $logId )->text(),
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => $ldata->log_action,
					];

					$buildLog["{$logId}-state"] = [
						'type' => 'select',
						'label' => wfMessage( 'incidentreporting-log-state', $logId )->text(),
						'options' => [
							wfMessage( 'incidentreporting-log-up' )->text() => 'up',
							wfMessage( 'incidentreporting-log-partial' )->text() => 'partial',
							wfMessage( 'incidentreporting-log-down' )->text() => 'down'
						],
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => $ldata->log_state
					];
				}
			}

			for ( $newId = $logId + 1; $newId <= $logId + 10; $newId++ ) {
				$buildLog["{$newId}-timestamp"] = [
					'type' => 'datetime',
					'label' => wfMessage( 'incidentreporting-log-timestamp', $newId )->text(),
					'section' => 'logs',
					'subsection' => (string)$newId
				];

				$buildLog["{$newId}-actor"] = [
					'type' => 'select',
					'label' => wfMessage( 'incidentreporting-log-actor', $newId )->text(),
					'options' => [
						wfMessage( 'incidentreporting-log-actor-information' )->text() => 'information',
						wfMessage( 'incidentreporting-log-actor-user' )->text() => 'user'
					],
					'section' => 'logs',
					'subsection' => (string)$newId
				];

				$buildLog["{$newId}-user"] = [
					'type' => 'user',
					'label' => wfMessage( 'incidentreporting-log-user', $newId )->text(),
					'exists' => true,
					'section' => 'logs',
					'subsection' => (string)$newId,
					'hide-if' => [ '!==', "{$newId}-actor", 'user' ]
				];

				$buildLog["{$newId}-action"] = [
					'type' => 'text',
					'label' => wfMessage( 'incidentreporting-log-action', $newId )->text(),
					'section' => 'logs'
				];

				$buildLog["{$newId}-state"] = [
					'type' => 'select',
					'label' => wfMessage( 'incidentreporting-log-state', $newId )->text(),
					'options' => [
						wfMessage( 'incidentreporting-log-up' )->text() => 'up',
						wfMessage( 'incidentreporting-log-partial' )->text() => 'partial',
						wfMessage( 'incidentreporting-log-down' )->text() => 'down'
					],
					'section' => 'logs',
					'subsection' => (string)$newId
				];
			}

			$buildLog['logs-number'] = [
				'type' => 'hidden',
				'default' => (string)$newId,
				'section' => 'logs'
			];
		}

		// actionables
		if ( $action == 'view' ) {
			$aArray = json_decode( $data->i_tasks, true );
			$tasks = [];

			foreach ( $aArray as $task ) {
				$tasks[] = Html::element( 'a', [ 'href' => $this->config->get( 'IncidentReportingTaskUrl' ) . $task ], $task );
			}

			$viewDescriptor['actionables'] = [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-actionables',
				'section' => 'main',
				'raw' => true,
				'default' => ( count( $tasks ) != 0 ) ? implode( "\n", $tasks ) : wfMessage( 'incidentreporting-label-no-actionables' )->parse()
			];
		} else {
			$buildDescriptor['actionables'] = [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-actionables',
				'section' => 'main',
				'default' => ( $data !== null ) ? implode( "\n", json_decode( $data->i_tasks, true ) ) : ''
			];
		}

		$buildDescriptor[$action] = [
			'type' => 'submit',
			'default' => wfMessage( "incidentreporting-{$action}" )->text(),
			'section' => 'main'
		];

		if ( $this->permissionManager->userHasRight( $context->getUser(), 'editincidents' ) ) {
			$viewDescriptor['view'] = [
				'type' => 'submit',
				'default' => wfMessage( 'incidentreporting-view' )->text(),
				'section' => 'main'
			];
		}

		if ( $action == 'view' ) {
			$formDescriptor = array_merge( $viewDescriptor, $buildLog );
		} else {
			$formDescriptor = array_merge( $buildDescriptor, $buildLog );
		}

		return $formDescriptor;
	}

	public function getForm(
		int $id,
		bool $edit,
		IContextSource $context,
		$formClass = IncidentReportingOOUIForm::class
	) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-incidentreporting' );

		$formDescriptor = $this->getFormDescriptor( $id, $edit, $context, $dbw );

		$htmlForm = new $formClass( $formDescriptor, $context, 'incidentreporting' );

		$htmlForm->setId( 'incidentreporting-form' );
		$htmlForm->suppressDefaultSubmit();
		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $id, $context, $dbw ) {
				return $this->submitForm( $formData, $form, $id, $context, $dbw );
			}
		);

		$irUser = $context->getUser()->getName();

		$isReviewer = $dbw->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'incidents_reviewer' )
			->where( [
				'r_incident' => $id,
				'r_user' => $irUser,
			] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( $isReviewer && !$isReviewer->r_timestamp ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'incidents_reviewer' )
				->set( [ 'r_timestamp' => $dbw->timestamp() ] )
				->where( [
					'r_incident' => $id,
					'r_user' => $irUser,
				] )
				->caller( __METHOD__ )
				->execute();
		}

		return $htmlForm;
	}

	protected function submitForm(
		array $formData,
		HTMLForm $form,
		int $id,
		IContextSource $context,
		IDatabase $dbw
	) {
		if ( isset( $formData['view'] ) && $formData['view'] ) {
			header( 'Location: ' . SpecialPage::getTitleFor( 'IncidentReports' )->getFullURL() . '/' . $id . '/edit' );
			return true;
		}

		// Handle main data for the incident
		$dbIncident = [
			'i_service' => $formData['service'],
			'i_cause' => $formData['cause'],
			'i_aggravation' => ( $formData['control-aggravation'] ) ? $formData['aggravation'] : null,
			'i_known' => ( $formData['control-known'] ) ? $formData['known'] : null,
			'i_preventable' => ( $formData['control-preventable'] ) ? $formData['preventable'] : null,
			'i_other' => ( $formData['control-other'] ) ? $formData['other'] : null,
			'i_responders' => $formData['responders'],
			'i_tasks' => ( $formData['actionables'] ) ? json_encode( explode( "\n", $formData['actionables'] ) ) : "[]"
		];

		if ( isset( $formData['publish'] ) && $formData['publish'] ) {
			$dbIncident['i_published'] = $dbw->timestamp();
		}

		if ( $id != 0 ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'incidents' )
				->set( $dbIncident )
				->where( [ 'i_id' => $id ] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'incidents' )
				->row( $dbIncident )
				->caller( __METHOD__ )
				->execute();

			$id = $dbw->newSelectQueryBuilder()
				->select( 'i_id' )
				->from( 'incidents' )
				->where( $dbIncident )
				->caller( __METHOD__ )
				->fetchField();
		}

		// Handle reviewers
		if ( $formData['review'] ) {
			$reviewers = explode( "\n", $formData['review'] );

			$dbReviewers = $dbw->newSelectQueryBuilder()
				->select( 'r_user' )
				->from( 'incidents_reviewer' )
				->where( [ 'r_incident' => $id ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $dbReviewers as $db ) {
				$reviewers = array_diff( $reviewers, (array)$db->r_user );
			}

			foreach ( $reviewers as $reviewer ) {
				$dbw->newInsertQueryBuilder()
					->insertInto( 'incidents_reviewer' )
					->row( [
						'r_incident' => $id,
						'r_user' => $reviewer,
						'r_timestamp' => null,
					] )
					->caller( __METHOD__ )
					->execute();
			}
		}

		// Handle events
		$eventNumber = (int)$formData['logs-number'];

		for ( $eId = 1; $eId < $eventNumber; $eId++ ) {
			if ( !(bool)$formData["{$eId}-timestamp"] ) {
				continue;
			}

			$dbEvent = [
				'log_incident' => $id,
				'log_id' => $eId,
				'log_actor' => ( $formData["{$eId}-user"] ) ?? $formData["{$eId}-actor"],
				'log_action' => $formData["{$eId}-action"],
				'log_timestamp' => wfTimestamp( TS_UNIX, $formData["{$eId}-timestamp"] ),
				'log_state' => $formData["{$eId}-state"]
			];

			$exists = $dbw->newSelectQueryBuilder()
				->select( ISQLPlatform::ALL_ROWS )
				->from( 'incidents_log' )
				->where( [
					'log_id' => $eId,
					'log_incident' => $id,
				] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( $exists ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'incidents_log' )
					->set( $dbEvent )
					->where( [
						'log_id' => $eId,
						'log_incident' => $id,
					] )
					->caller( __METHOD__ )
					->execute();
			} else {
				$dbw->newInsertQueryBuilder()
					->insertInto( 'incidents_log' )
					->row( $dbEvent )
					->caller( __METHOD__ )
					->execute();
			}
		}

		// Outage data
		$logData = $dbw->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'incidents_log' )
			->where( [ 'log_incident' => $id ] )
			->orderBy( 'log_timestamp', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->fetchResultSet();

		$outageTotal = 0;
		$outageVisible = 0;
		$curState = null;
		$curTime = null;

		foreach ( $logData as $odata ) {
			$workTime = ( ( $curTime !== null ) ? $odata->log_timestamp - $curTime : 0 ) / 60;

			if ( $odata->log_state == 'down' || ( $odata->log_state != 'down' && $curState == 'down' ) ) {
				$outageVisible += $workTime;
			}

			if ( $curState != 'up' ) {
				$outageTotal += $workTime;
			}

			$curState = $odata->log_state;
			$curTime = $odata->log_timestamp;
		}

		$dbw->newUpdateQueryBuilder()
			->update( 'incidents' )
			->set( [
				'i_outage_total' => round( $outageTotal ),
				'i_outage_visible' => round( $outageVisible ),
			] )
			->where( [ 'i_id' => $id ] )
			->caller( __METHOD__ )
			->execute();

		$published = $dbw->newSelectQueryBuilder()
			->select( 'i_published' )
			->from( 'incidents' )
			->where( [ 'i_id' => $id ] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $published ) {
			$mainTitle = substr( $form->getTitle()->getText(), 0, -5 );

			$irLogEntry = new ManualLogEntry( 'incidentreporting', 'modify' );
			$irLogEntry->setPerformer( $form->getContext()->getUser() );
			$irLogEntry->setTarget( Title::newFromText( $mainTitle, NS_SPECIAL ) );
			$irLogID = $irLogEntry->insert();
			$irLogEntry->publish( $irLogID );
		}

		$context->getOutput()->addHTML( Html::successBox( wfMessage( 'incidentreporting-success' )->parse() ) );

		return true;
	}
}
