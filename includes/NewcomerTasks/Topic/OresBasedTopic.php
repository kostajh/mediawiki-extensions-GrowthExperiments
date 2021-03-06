<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

class OresBasedTopic extends Topic {

	/** @var string[] */
	private $oresTopics;

	/**
	 * @param string $id Topic ID, a string consisting of lowercase alphanumeric characters
	 *   and dashes. E.g. 'biology'.
	 * @param string|null $groupId Topic group, for visual grouping. E.g. 'science'.
	 * @param string[] $oresTopics ORES topic IDs which define this topic.
	 */
	public function __construct( string $id, string $groupId, array $oresTopics ) {
		parent::__construct( $id, $groupId );
		$this->oresTopics = $oresTopics;
	}

	/**
	 * ORES topic IDs which define this topic.
	 * @return string[]
	 */
	public function getOresTopics(): array {
		return $this->oresTopics;
	}

}
