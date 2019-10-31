<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Http\HttpRequestFactory;
use Status;
use StatusValue;
use TitleFactory;

class TaskSuggesterFactory {

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 */
	public function __construct( ConfigurationLoader $configurationLoader ) {
		$this->configurationLoader = $configurationLoader;
	}

	/**
	 * Create a TaskSuggester which uses a public search API.
	 * @param HttpRequestFactory $requestFactory
	 * @param TitleFactory $titleFactory
	 * @param string $apiUrl Base URL of the remote API (ending with 'api.php').
	 * @return TaskSuggester
	 */
	public function createRemote(
		HttpRequestFactory $requestFactory,
		TitleFactory $titleFactory,
		$apiUrl
	) {
		$taskTypes = $this->configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $this->createError( $taskTypes );
		}
		$templateBlacklist = $this->configurationLoader->loadTemplateBlacklist();
		if ( $templateBlacklist instanceof StatusValue ) {
			return $this->createError( $templateBlacklist );
		}
		return new RemoteSearchTaskSuggester( $requestFactory, $titleFactory, $apiUrl, $taskTypes,
			$templateBlacklist );
	}

	/**
	 * Create a TaskSuggester which just returns a given error.
	 * @param StatusValue $status
	 * @return ErrorForwardingTaskSuggester
	 */
	protected function createError( StatusValue $status ) {
		$msg = Status::wrap( $status )->getWikiText( null, null, 'en' );
		Util::logError( new WikiConfigException( $msg ) );
		return new ErrorForwardingTaskSuggester( $status );
	}

}