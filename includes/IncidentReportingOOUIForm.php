<?php

namespace Miraheze\IncidentReporting;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\OOUIHTMLForm;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\IndexLayout;
use OOUI\PanelLayout;
use OOUI\TabPanelLayout;
use OOUI\Widget;

class IncidentReportingOOUIForm extends OOUIHTMLForm {
	/** @var bool */
	protected $mSubSectionBeforeFields = false;

	public function wrapForm( $html ) {
		$html = Html::rawElement( 'div', [ 'id' => 'incidentreporting' ], $html );
		return parent::wrapForm( $html );
	}

	protected function wrapFieldSetSection( $legend, $section, $attributes, $isRoot ) {
		$layout = parent::wrapFieldSetSection( $legend, $section, $attributes, $isRoot );

		$layout->addClasses( [ 'incidentreporting-fieldset-wrapper' ] );
		$layout->removeClasses( [ 'oo-ui-panelLayout-framed' ] );

		return $layout;
	}

	public function getBody() {
		$tabPanels = [];
		foreach ( $this->mFieldTree as $key => $val ) {
			if ( !is_array( $val ) ) {
				wfDebug( __METHOD__ . " encountered a field not attached to a section: '{$key}'" );

				continue;
			}

			$label = $this->getLegend( $key );

			$content =
				$this->getHeaderHtml( $key ) .
				$this->displaySection(
					$val,
					'',
					"mw-section-{$key}-"
				) .
				$this->getFooterHtml( $key );

			$tabPanels[] = new TabPanelLayout( 'mw-section-' . $key, [
				'classes' => [ 'mw-htmlform-autoinfuse-lazy' ],
				'label' => $label,
				'content' => new FieldsetLayout( [
					'classes' => [ 'incidentreporting-section-fieldset' ],
					'id' => "mw-section-{$key}",
					'label' => $label,
					'items' => [
						new Widget( [
							'content' => new HtmlSnippet( $content )
						] ),
					],
				] ),
				'expanded' => false,
				'framed' => true,
			] );
		}

		$indexLayout = new IndexLayout( [
			'infusable' => true,
			'expanded' => false,
			'autoFocus' => false,
			'classes' => [ 'incidentreporting-tabs' ],
		] );

		$indexLayout->addTabPanels( $tabPanels );

		$header = $this->formatFormHeader();

		$form = new PanelLayout( [
			'framed' => true,
			'expanded' => false,
			'classes' => [ 'incidentreporting-tabs-wrapper' ],
			'content' => $indexLayout
		] );

		return $header . $form;
	}
}
