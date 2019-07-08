<?php

use GrowthExperiments\Api\ApiHelpPanelPostQuestion;
use MediaWiki\Block\DatabaseBlock;

/**
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \GrowthExperiments\Api\ApiHelpPanelPostQuestion
 */
class ApiHelpPanelQuestionPosterTest extends ApiTestCase {

	/**
	 * @var User
	 */
	protected $mUser = null;

	public function setUp() {
		parent::setUp();
		$this->mUser = $this->getMutableTestUser()->getUser();
		$this->setMwGlobals( [
			'wgEnableEmail' => true,
			'wgGEHelpPanelHelpDeskTitle' => 'HelpDeskTest',
		] );
		$this->editPage( 'HelpDeskTest', 'Content' );
	}

	protected function getParams( $body, $email = '', $relevanttitle = '' ) {
		$params = [
			'action' => 'helppanelquestionposter',
			ApiHelpPanelPostQuestion::API_PARAM_BODY => $body,
		];
		if ( $email ) {
			$params += [ ApiHelpPanelPostQuestion::API_PARAM_EMAIL => $email ];
		}
		if ( $relevanttitle ) {
			$params += [ ApiHelpPanelPostQuestion::API_PARAM_RELEVANT_TITLE => $relevanttitle ];
		}
		return $params;
	}

	/**
	 * @covers \GrowthExperiments\Api\ApiHelpPanelPostQuestion::execute
	 */
	public function testExecute() {
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
			'result' => 'success',
			'isfirstedit' => true
		], $ret[0]['helppanelquestionposter'] );
		$this->assertGreaterThan( 0, $ret[0]['helppanelquestionposter'] );
	}

	public function testHandleNoEmail() {
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => 'blah@blahblah.com'
		];

		$this->mUser->setEmail( '' );
		$this->mUser->saveSettings();
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
			'result' => 'success',
			'email' => 'set_email_with_confirmation'
		], $ret[0]['helppanelquestionposter'] );
	}

	public function testHandleNoEmailNoOp() {
		// no email -> no email.
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => ''
		];

		$updateUser = $this->mUser->getInstanceForUpdate();
		$updateUser->setEmail( '' );
		$updateUser->saveSettings();
		$ret = $this->doApiRequestWithToken( $params, null, $updateUser, 'csrf' );
		$this->assertArraySubset( [
			'result' => 'success',
			'email' => 'no_op'
		], $ret[0]['helppanelquestionposter'] );
	}

	public function testInvalidEmail() {
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => '123'
		];
		$this->mUser->setEmail( 'a@b.com' );
		$this->mUser->saveSettings();
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
			'result' => 'success',
			'email' => 'Insufficient permissions to set email.'
		], $ret[0]['helppanelquestionposter'] );
	}

	public function testBlankEmailFromUnconfirmedEmail() {
		$params = [
			'action' => 'helppanelquestionposter',
			'body' => 'lorem ipsum',
			'email' => ''
		];

		$this->mUser->setEmail( 'a@b.com' );
		$this->mUser->saveSettings();
		$this->assertEquals( 'a@b.com', $this->mUser->getEmail() );
		$ret = $this->doApiRequestWithToken( $params, null, $this->mUser, 'csrf' );
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'no_op'
		], $ret[0]['helppanelquestionposter'] );
		$this->assertEquals( 'a@b.com', $this->mUser->getInstanceForUpdate()->getEmail() );
	}

	public function testHandleUnconfirmedEmail() {
		$updateUser = $this->mUser->getInstanceForUpdate();
		$updateUser->setEmail( 'a@b.com' );
		$updateUser->saveSettings();
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem ipsum', 'blah@blah.com' ),
			null,
			$updateUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'Insufficient permissions to set email.'
			], $ret[0]['helppanelquestionposter'] );

		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'blah', 'change@again.com' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'Insufficient permissions to set email.'
			], $ret[0]['helppanelquestionposter'] );
	}

	public function testHandleUnconfirmedEmailSendConfirm() {
		$updateUser = $this->mUser->getInstanceForUpdate();
		$updateUser->setEmail( 'a@b.com' );
		$updateUser->saveSettings();
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'blah', 'a@b.com' ),
			null,
			$updateUser,
			'csrf'
		);
		$this->assertArraySubset( [
				'result' => 'success',
				'email' => 'Insufficient permissions to set email.'
			], $ret[0]['helppanelquestionposter'] );
	}

	public function testHandleConfirmedEmail() {
		// User attempts to change confirmed email.
		$updateUser = $this->mUser->getInstanceForUpdate();
		$updateUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$updateUser->saveSettings();
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', 'shouldthrow@error.com' ),
			null,
			$updateUser,
			'csrf'
		);
		$this->assertArraySubset(
			[ 'email' => 'Insufficient permissions to set email.' ],
			$ret[0]['helppanelquestionposter']
		);
		// No change with confirmed email.
		// User attempts to change confirmed email.
		$updateUser = $this->mUser->getInstanceForUpdate();
		$updateUser->setEmail( 'a@b.com' );
		$updateUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$updateUser->saveSettings();
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', 'a@b.com' ),
			null,
			$updateUser,
			'csrf'
		);
		$this->assertArraySubset(
			[ 'email' => 'Insufficient permissions to set email.' ],
			$ret[0]['helppanelquestionposter']
		);

		// User attempts to blank confirmed email.
		$updateUser = $this->mUser->getInstanceForUpdate();
		$updateUser->setEmail( 'a@b.com' );
		$updateUser->setEmailAuthenticationTimestamp( wfTimestamp() );
		$updateUser->saveSettings();
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'lorem', '' ),
			null,
			$updateUser,
			'csrf'
		);
		$this->assertArraySubset(
			[ 'email' => 'no_op' ],
			$ret[0]['helppanelquestionposter']
		);
	}

	public function testValidRelevantTitle() {
		$this->editPage( 'Real', 'Content' );
		$ret = $this->doApiRequestWithToken(
			$this->getParams( 'a', null, 'Real' ),
			null,
			$this->mUser,
			'csrf'
		);
		$this->assertArraySubset( [
			'result' => 'success',
		], $ret[0]['helppanelquestionposter'] );
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::checkUserPermissions
	 * @expectedException ApiUsageException
	 * @expectedExceptionMessageRegExp /Your username or IP address has been blocked/
	 */
	public function testBlockedUserCantPostQuestion() {
		$block = new DatabaseBlock();
		$block->setTarget( $this->mUser );
		$block->setBlocker( $this->getTestSysop()->getUser() );
		$block->insert();
		$this->doApiRequestWithToken(
			$this->getParams( 'user is blocked' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

	/**
	 * @covers \GrowthExperiments\HelpPanel\QuestionPoster::runEditFilterMergedContentHook
	 * @expectedException ApiUsageException
	 */
	public function testEditFilterMergedContentHookReturnsFalse() {
		$this->setTemporaryHook( 'EditFilterMergedContent',
			function ( $unused1, $unused2, Status $status ) {
				$status->setOK( false );
				return false;
			}
		);
		$this->doApiRequestWithToken(
			$this->getParams( 'abuse filter denies edit' ),
			null,
			$this->mUser,
			'csrf'
		);
	}

}