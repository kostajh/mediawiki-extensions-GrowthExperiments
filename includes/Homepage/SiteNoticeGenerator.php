<?php

namespace GrowthExperiments\Homepage;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\Util;
use Html;
use OOUI\IconWidget;
use OutputPage;
use UserOptionsUpdateJob;

class SiteNoticeGenerator {

	/**
	 * @param string $name
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param bool &$minervaEnableSiteNotice Reference to $wgMinervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	public static function setNotice( $name, &$siteNotice, \Skin $skin, &$minervaEnableSiteNotice ) {
		if ( $skin->getTitle()->isSpecial( 'WelcomeSurvey' ) ) {
			// Don't show any notices on the welcome survey.
			return;
		}
		switch ( $name ) {
			case HomepageHooks::CONFIRMEMAIL_QUERY_PARAM:
				if ( $skin->getTitle()->isSpecial( 'Homepage' ) ) {
					return self::setConfirmEmailSiteNotice( $siteNotice, $skin, $minervaEnableSiteNotice );
				}
				break;
			case 'specialwelcomesurvey':
				if ( $skin->getTitle()->isSpecial( 'Homepage' ) ) {
					return self::setDiscoverySiteNotice( $siteNotice, $skin, $name, $minervaEnableSiteNotice );
				}
				break;
			case 'welcomesurvey-originalcontext':
				if ( !$skin->getTitle()->isSpecial( 'Homepage' ) ) {
					return self::setDiscoverySiteNotice( $siteNotice, $skin, $name, $minervaEnableSiteNotice );
				}
				break;
			default:
				return self::maybeShowIfUserAbandonedWelcomeSurvey(
					$siteNotice,
					$skin,
					$minervaEnableSiteNotice );
		}
	}

	private static function maybeShowIfUserAbandonedWelcomeSurvey(
		&$siteNotice, \Skin $skin, &$minervaEnableSiteNotice
	) {
		if ( self::isWelcomeSurveyInReferer( $skin )
			|| Util::isMobile( $skin ) && !self::checkAndMarkMobileDiscoveryNoticeSeen( $skin )
		) {
			return self::setDiscoverySiteNotice(
				$siteNotice,
				$skin,
				$skin->getTitle()->isSpecial( 'Homepage' )
					? 'specialwelcomesurvey'
					: 'welcomesurvey-originalcontext',
				$minervaEnableSiteNotice
			);
		}
	}

	private static function isWelcomeSurveyInReferer( \Skin $skin ) {
		foreach ( $skin->getLanguage()->getSpecialPageAliases()['WelcomeSurvey'] as $alias ) {
			if ( strpos( $skin->getRequest()->getHeader( 'REFERER' ), $alias ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param bool &$minervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	private static function setConfirmEmailSiteNotice(
		&$siteNotice, \Skin $skin, &$minervaEnableSiteNotice
	) {
		$output = $skin->getOutput();
		$output->addModules( 'ext.growthExperiments.Homepage.ConfirmEmail' );
		$output->addModuleStyles( 'ext.growthExperiments.Homepage.ConfirmEmail.styles' );
		$baseCssClassName = 'mw-ge-homepage-confirmemail-nojs';
		$cssClasses = [
			$baseCssClassName,
			Util::isMobile( $skin ) ? $baseCssClassName . '-mobile' : $baseCssClassName . '-desktop'
		];
		$siteNotice = Html::rawElement( 'div', [ 'class' => $cssClasses ],
			new IconWidget( [ 'icon' => 'check', 'flags' => 'success' ] ) . ' ' .
			Html::element( 'span', [ 'class' => 'mw-ge-homepage-confirmemail-nojs-message' ],
				$output->msg( 'confirmemail_loggedin' )->text() )
		);
		$minervaEnableSiteNotice = true;
		// Only triggered for a specific source query parameter, which the user should see only
		// once, so it's OK to suppress all other banners.
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param string $contextName
	 * @param bool &$minervaEnableSiteNotice
	 * @return bool|void Hook return value (ie. false to prevent other notices from displaying)
	 */
	private static function setDiscoverySiteNotice(
		&$siteNotice, \Skin $skin, $contextName, &$minervaEnableSiteNotice
	) {
		if ( Util::isMobile( $skin ) ) {
			self::setMobileDiscoverySiteNotice( $siteNotice, $skin, $contextName, $minervaEnableSiteNotice );
			self::checkAndMarkMobileDiscoveryNoticeSeen( $skin );
		} else {
			self::setDesktopDiscoverySiteNotice( $siteNotice, $skin, $contextName );
		}
		// Only triggered for a specific source query parameter, which the user should see only
		// once, so it's OK to suppress all other banners.
		return false;
	}

	/**
	 * Check and set seen flag for the mobile homapage discovery sitenotice.
	 * (Desktop uses a different mechanism based on guided tours, which has its own seen logic.)
	 * @param \Skin $skin
	 * @return bool True if the user has seen the notice already.
	 * @suppress PhanUndeclaredProperty
	 */
	private static function checkAndMarkMobileDiscoveryNoticeSeen( \Skin $skin ) {
		// Make multiple calls to this method within the same request a no-op.
		// Note this would be necessary even if we only called it once, because
		// Minerva calls sitenotice hooks multiple times.
		if ( isset( $skin->growthExperimentsHomepageDiscoveryNoticeSeen ) ) {
			return $skin->growthExperimentsHomepageDiscoveryNoticeSeen;
		}

		$user = $skin->getUser();
		if ( $user->getOption( HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN ) ) {
			$skin->growthExperimentsHomepageDiscoveryNoticeSeen = true;
			return true;
		}

		\JobQueueGroup::singleton()->lazyPush( new UserOptionsUpdateJob( [
			'userId' => $user->getId(),
			'options' => [ HomepageHooks::HOMEPAGE_MOBILE_DISCOVERY_NOTICE_SEEN => 1 ],
		] ) );
		$skin->growthExperimentsHomepageDiscoveryNoticeSeen = false;
		return false;
	}

	/**
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param string $contextName
	 */
	private static function setDesktopDiscoverySiteNotice(
		&$siteNotice, \Skin $skin, $contextName
	) {
		// No-JS banner (hidden from CSS when there's JS support). The JS version is in
		// ext.growthExperiments.homepage.discovery.tour.js.

		$output = $skin->getOutput();
		$output->enableOOUI();
		$output->addModuleStyles( [
			'oojs-ui.styles.icons-user',
			'ext.growthExperiments.Homepage.Discovery.styles'
		] );

		$username = $skin->getUser()->getName();
		if ( $contextName === 'specialwelcomesurvey' ) {
			$msgHeaderKey = 'growthexperiments-homepage-discovery-banner-header';
			$msgBodyKey = 'growthexperiments-homepage-discovery-banner-text';
		} else {
			$msgHeaderKey = 'growthexperiments-homepage-discovery-thanks-header';
			$msgBodyKey = 'growthexperiments-homepage-discovery-thanks-text';
		}
		$siteNotice = Html::rawElement( 'div', [ 'class' => 'mw-ge-homepage-discovery-banner-nojs' ],
			Html::element( 'span', [ 'class' => 'mw-ge-homepage-discovery-house' ] ) .
			Html::rawElement( 'span', [ 'class' => 'mw-ge-homepage-discovery-text-content' ],
				Html::element( 'h2', [ 'class' => 'mw-ge-homepage-discovery-nojs-message' ],
					$output->msg( $msgHeaderKey )->params( $username )->text() ) .
				self::getDiscoveryTextWithAvatarIcon(
					$output,
					$msgBodyKey,
					$username,
					'mw-ge-homepage-discovery-nojs-banner-text'
				)
			)
		);
	}

	/**
	 * @param string &$siteNotice
	 * @param \Skin $skin
	 * @param string $contextName
	 * @param bool &$minervaEnableSiteNotice
	 */
	private static function setMobileDiscoverySiteNotice(
		&$siteNotice, \Skin $skin, $contextName, &$minervaEnableSiteNotice
	) {
		$output = $skin->getOutput();
		$output->enableOOUI();
		$output->addModuleStyles( [
			'oojs-ui.styles.icons-user',
			'ext.growthExperiments.Homepage.Discovery.styles'
		] );
		$output->addModules( 'ext.growthExperiments.Homepage.Discovery.scripts' );

		$username = $skin->getUser()->getName();
		$location = ( $contextName === 'specialwelcomesurvey' ) ? 'homepage' : 'nonhomepage';
		$msgHeaderKey = "growthexperiments-homepage-discovery-mobile-$location-banner-header";
		$msgBodyKey = "growthexperiments-homepage-discovery-mobile-$location-banner-text";

		$siteNotice = Html::rawElement( 'div', [ 'class' => 'mw-ge-homepage-discovery-banner-mobile' ],
			Html::element( 'div', [ 'class' => 'mw-ge-homepage-discovery-arrow' ] ) .
			Html::rawElement( 'div', [ 'class' => 'mw-ge-homepage-discovery-message' ],
				Html::element( 'h2', [],
					$output->msg( $msgHeaderKey )->params( $username )->text() ) .
				self::getDiscoveryTextWithAvatarIcon( $output, $msgBodyKey, $username )
			) . new IconWidget( [ 'icon' => 'close',
				'classes' => [ 'mw-ge-homepage-discovery-banner-close' ] ] )
		);

		$minervaEnableSiteNotice = true;
	}

	private static function getDiscoveryTextWithAvatarIcon(
		OutputPage $output, $msgBodyKey, $username, $class = ''
	) {
		return Html::rawElement( 'p', [ 'class' => $class ],
			$output->msg( $msgBodyKey )
				->params( $username )
				->rawParams(
					new IconWidget( [ 'icon' => 'userAvatar' ] ) .
					// add a word joiner to make the icon stick to the name
					\UtfNormal\Utils::codepointToUtf8( 0x2060 ) .
					Html::element( 'span', [], $username )
				)->parse()
		);
	}

}
