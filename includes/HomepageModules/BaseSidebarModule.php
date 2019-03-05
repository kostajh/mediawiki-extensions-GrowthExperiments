<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HomepageModule;
use Html;
use IContextSource;

/**
 * Class BaseSidebarModule is a base class for a small homepage module
 * typically displayed in the sidebar.
 *
 * @package GrowthExperiments\HomepageModules
 */
abstract class BaseSidebarModule implements HomepageModule {

	const BASE_CSS_CLASS = 'growthexperiments-homepage-module';

	/**
	 * @var IContextSource
	 */
	private $ctx;

	/**
	 * @var string Name of the module
	 */
	private $name;

	/**
	 * @param string $name Name of the module
	 */
	public function __construct( $name ) {
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	public function render( IContextSource $ctx ) {
		$this->ctx = $ctx;
		$out = $ctx->getOutput();
		$out->addModuleStyles( 'ext.growthExperiments.Homepage.BaseSidebarModule.styles' );
		$out->addModuleStyles( $this->getModuleStyles() );
		$out->addModules( $this->getModules() );
		$out->addHTML( Html::rawElement(
			'div',
			[ 'class' => [ self::BASE_CSS_CLASS, self::BASE_CSS_CLASS . '-' . $this->name ] ],
			$this->buildSection( 'header', $this->getHeader(), 'h2' ) .
			$this->buildSection( 'subheader', $this->getSubheader(), 'h3' ) .
			$this->buildSection( 'body', $this->getBody() ) .
			$this->buildSection( 'footer', $this->getFooter() )
		) );
	}

	/**
	 * @return IContextSource Current context
	 */
	final protected function getContext() {
		return $this->ctx;
	}

	/**
	 * Implement this function to provide the module header.
	 *
	 * @return string Text or HTML content of the header
	 */
	abstract protected function getHeader();

	/**
	 * Implement this function to provide the module body.
	 *
	 * @return string Text or HTML content of the body
	 */
	abstract protected function getBody();

	/**
	 * Override this function to provide an optional module subheader.
	 *
	 * @return string Text or HTML content of the subheader
	 */
	protected function getSubheader() {
		return '';
	}

	/**
	 * Override this function to provide an optional module footer.
	 *
	 * @return string Text or HTML content of the footer
	 */
	protected function getFooter() {
		return '';
	}

	/**
	 * Override this function to provide module styles that need to be
	 * loaded in the <head> for this module.
	 *
	 * @return string|string[] Name of the module(s) to load
	 */
	protected function getModuleStyles() {
		return '';
	}

	/**
	 * Override this function to provide modules that need to be
	 * loaded for this module.
	 *
	 * @return string|string[] Name of the module(s) to load
	 */
	protected function getModules() {
		return '';
	}

	/**
	 * Build a module section
	 *
	 * @param string $name Name of the section, used to generate a class
	 * @param string $content Text or HTML content of the section
	 * @param string $tag HTML tag to use for the section
	 * @return string
	 */
	private function buildSection( $name, $content, $tag = 'div' ) {
		return $content ? Html::rawElement(
			$tag,
			[
				'class' => [
					self::BASE_CSS_CLASS . '-section',
					self::BASE_CSS_CLASS . '-' . $name,
				]
			],
			$content
		) : '';
	}

}