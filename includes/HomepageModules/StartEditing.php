<?php

namespace GrowthExperiments\HomepageModules;

use FormatJson;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\WelcomeSurvey;
use IContextSource;
use OOUI\ButtonWidget;

class StartEditing extends BaseTaskModule {

	/** @var bool In-process cache for isCompleted() */
	private $isCompleted;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-startediting', $context );
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		if ( $this->isCompleted === null ) {
			$this->isCompleted =
				$this->getContext()->getUser()->getBoolOption( SuggestedEdits::ACTIVATED_PREF );
		}
		return $this->isCompleted;
	}

	/**
	 * @inheritDoc
	 */
	public function isVisible() {
		return ( $this->getMode() !== HomepageModule::RENDER_DESKTOP ) || !$this->isCompleted();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'edit';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		if ( $this->isCompleted() &&
			$this->getMode() === HomepageModule::RENDER_MOBILE_SUMMARY
		) {
			return $this->getContext()->msg( 'growthexperiments-homepage-startediting-button' )->text();
		} else {
			return $this->getContext()->msg( 'growthexperiments-homepage-startediting-header' )->text();
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		// Decide which message to use based on the user's WelcomeSurvey response. Messages:
		// growthexperiments-homepage-startediting-subheader-edit-typo
		// growthexperiments-homepage-startediting-subheader-add-image
		// growthexperiments-homepage-startediting-subheader-new-page
		$surveyResponse = FormatJson::decode(
			$this->getContext()->getUser()->getOption( WelcomeSurvey::SURVEY_PROP, '' )
		)->reason ?? 'none';
		$msgKey = "growthexperiments-homepage-startediting-subheader-$surveyResponse";

		// Fall back on -other if there is no specific message for this response
		if ( !$this->getContext()->msg( $msgKey )->exists() ) {
			$msgKey = 'growthexperiments-homepage-startediting-subheader-other';
		}
		return $this->getContext()->msg( $msgKey )->escaped();
	}

	/**
	 * @inheritDoc
	 */
	protected function getFooter() {
		return new ButtonWidget( [
			'id' => 'mw-ge-homepage-startediting-cta',
			'label' => $this->getContext()->msg( 'growthexperiments-homepage-startediting-button' )->text(),
			'flags' => [ 'progressive', 'primary' ],
			'active' => false,
			'infusable' => true,
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getModules() {
		return array_merge(
			parent::getModules(),
			[ 'ext.growthExperiments.Homepage.StartEditing' ]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-editing-core' ]
		);
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GEHomepageSuggestedEditsEnableTopics' =>
				SuggestedEdits::isTopicMatchingEnabled( $this->getContext() )
		];
	}
}
