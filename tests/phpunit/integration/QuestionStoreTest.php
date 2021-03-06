<?php

namespace GrowthExperiments\Tests;

use DerivativeContext;
use FauxRequest;
use GrowthExperiments\HelpPanel\HelpPanelQuestionPoster;
use GrowthExperiments\HelpPanel\QuestionRecord;
use GrowthExperiments\HelpPanel\QuestionStore;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use RequestContext;

/**
 * @group Database
 * @group medium
 */
class QuestionStoreTest extends MediaWikiTestCase {

	protected $tablesUsed = [ 'user_properties' ];

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStore::__construct
	 */
	public function testValidConstructionDoesntCauseErrors() {
		$questionStore = new QuestionStore(
			$this->getTestSysop()->getUser(),
			'foo',
			MediaWikiServices::getInstance()->getRevisionStore(),
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			MediaWikiServices::getInstance()->getContentLanguage(),
			RequestContext::getMain()->getRequest()->wasPosted()
		);
		$this->assertInstanceOf( QuestionStore::class, $questionStore );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionStore::add
	 */
	public function testQuestionAdd() {
		$context = new DerivativeContext( RequestContext::getMain() );
		$user = $this->getMutableTestUser()->getUser()->getInstanceForUpdate();
		$context->setRequest( new FauxRequest( [], true ) );
		$context->setUser( $user );
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$context,
			HelpPanelQuestionPoster::QUESTION_PREF
		);
		$timestamp = wfTimestamp();
		$question = new QuestionRecord(
			'foo',
			'bar',
			123,
			$timestamp,
			'https://mediawiki.org',
			CONTENT_MODEL_WIKITEXT
		);
		$questionStore->add( $question );
		$context->setUser( $user->getInstanceForUpdate() );
		$questionStore = QuestionStoreFactory::newFromContextAndStorage(
			$context,
			HelpPanelQuestionPoster::QUESTION_PREF
		);
		$loadedQuestions = $questionStore->loadQuestions();
		$loadedQuestion = current( $loadedQuestions );
		$this->assertArrayEquals(
			[
				'questionText' => 'foo',
				'sectionHeader' => 'bar',
				'revId' => 123,
				'resultUrl' => 'https://mediawiki.org',
				'archiveUrl' => '',
				'timestamp' => $timestamp,
				'isArchived' => false,
				'isVisible' => true,
				'contentModel' => CONTENT_MODEL_WIKITEXT,
			],
			$loadedQuestion->jsonSerialize()
		);
	}

}
