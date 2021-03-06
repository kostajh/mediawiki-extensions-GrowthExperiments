<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TemplateProvider;
use SearchEngineFactory;
use StatusValue;

/**
 * Factory for LocalSearchTaskSuggester.
 */
class LocalSearchTaskSuggesterFactory extends SearchTaskSuggesterFactory {

	/**
	 * @var SearchEngineFactory
	 */
	private $searchEngineFactory;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param SearchStrategy $searchStrategy
	 * @param TemplateProvider $templateProvider
	 * @param SearchEngineFactory $searchEngineFactory
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		SearchStrategy $searchStrategy,
		TemplateProvider $templateProvider,
		SearchEngineFactory $searchEngineFactory
	) {
		parent::__construct( $configurationLoader, $searchStrategy, $templateProvider );
		$this->searchEngineFactory = $searchEngineFactory;
	}

	/**
	 * @return LocalSearchTaskSuggester|ErrorForwardingTaskSuggester
	 */
	public function create() {
		$taskTypes = $this->configurationLoader->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return $this->createError( $taskTypes );
		}
		$topics = $this->configurationLoader->loadTopics();
		if ( $topics instanceof StatusValue ) {
			return $this->createError( $topics );
		}
		$templateBlacklist = $this->configurationLoader->loadTemplateBlacklist();
		if ( $templateBlacklist instanceof StatusValue ) {
			return $this->createError( $templateBlacklist );
		}
		$suggester = new LocalSearchTaskSuggester(
			$this->searchEngineFactory,
			$this->templateProvider,
			$this->searchStrategy,
			$taskTypes,
			$topics,
			$templateBlacklist
		);
		$suggester->setLogger( $this->logger );
		return $suggester;
	}

}
