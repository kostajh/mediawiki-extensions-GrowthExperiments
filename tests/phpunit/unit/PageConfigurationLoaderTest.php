<?php

namespace GrowthExperiments\Tests;

use Collation;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
use Message;
use MessageLocalizer;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use TitleValue;

/**
 * @coversDefaultClass  \GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader
 */
class PageConfigurationLoaderTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::loadTaskTypes
	 */
	public function testLoadTaskTypes() {
		$configurationLoader = $this->getConfigurationLoader( $this->getTaskConfig(), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockPageLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertIsArray( $taskTypes );
			$this->assertNotEmpty( $taskTypes );
			$this->assertInstanceOf( TaskType::class, $taskTypes[0] );
			$this->assertSame( [ 'copyedit', 'references' ], array_map( function ( TaskType $tt ) {
				return $tt->getId();
			}, $taskTypes ) );
			$this->assertSame( [ 'easy', 'medium' ], array_map( function ( TaskType $tt ) {
				return $tt->getDifficulty();
			}, $taskTypes ) );
		}

		$configurationLoader = $this->getConfigurationLoader( StatusValue::newFatal( 'foo' ),
			[], PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		foreach ( range( 1, 2 ) as $_ ) {
			$taskTypes = $configurationLoader->loadTaskTypes();
			$this->assertInstanceOf( StatusValue::class, $taskTypes );
			$this->assertTrue( $taskTypes->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTaskTypes
	 * @dataProvider provideLoadTaskTypes_error
	 */
	public function testLoadTaskTypes_error( $error ) {
		$msg = $this->createMock( Message::class );
		$msg->method( 'exists' )->willReturn( false );
		$configurationLoader = $this->getConfigurationLoader( $this->getTaskConfig( $error ), [],
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES,
			[ 'growthexperiments-homepage-suggestededits-tasktype-name-foo' => $msg ] );
		$status = $configurationLoader->loadTaskTypes();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadTaskTypes_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
			[ 'wronggroup' ],
			[ 'missingmessage' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadOresTopics() {
		$configurationLoader = $this->getConfigurationLoader( [], $this->getOresTopicConfig(),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockPageLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertIsArray( $topics );
			$this->assertNotEmpty( $topics );
			$this->assertInstanceOf( Topic::class, $topics[0] );
			$this->assertSame( [ 'art', 'food', 'science' ], array_map( function ( Topic $t ) {
				return $t->getId();
			}, $topics ) );
			$this->assertSame( [ [ 'music', 'painting' ], [ 'food' ], [ 'physics', 'biology' ] ],
				array_map( function ( OresBasedTopic $t ) {
					return $t->getOresTopics();
				}, $topics ) );
		}

		$configurationLoader = $this->getConfigurationLoader( [], StatusValue::newFatal( 'foo' ),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertInstanceOf( StatusValue::class, $topics );
			$this->assertTrue( $topics->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTopics
	 * @dataProvider provideLoadOresTopics_error
	 */
	public function testLoadOresTopics_error( $error ) {
		$configurationLoader = $this->getConfigurationLoader( [], $this->getOresTopicConfig( $error ),
			PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$status = $configurationLoader->loadTopics();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadOresTopics_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadMorelikeTopics() {
		$configurationLoader = $this->getConfigurationLoader( [], $this->getMorelikeTopicConfig(),
			PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		// Run twice to test caching. If caching is broken, the 'atMost(1)' expectation
		// in getMockPageLoader() will fail.
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertIsArray( $topics );
			$this->assertNotEmpty( $topics );
			$this->assertInstanceOf( Topic::class, $topics[0] );
			$this->assertSame( [ 'art', 'science' ], array_map( function ( Topic $t ) {
				return $t->getId();
			}, $topics ) );
			// FIXME can't test this while the RawMessage hack is used
			// $this->assertSame( [ 'Art', 'Science' ], array_map( function ( Topic $t ) {
			//	return $t->getName( $this->getMockMessageLocalizer() );
			// }, $topics ) );
			$this->assertSame( [ [ 'Music', 'Painting' ], [ 'Physics', 'Biology' ] ],
				array_map( function ( MorelikeBasedTopic $t ) {
					return array_map( function ( LinkTarget $lt ) {
						return $lt->getText();
					}, $t->getReferencePages() );
				}, $topics ) );
		}

		$configurationLoader = $this->getConfigurationLoader( [], StatusValue::newFatal( 'foo' ),
			PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		foreach ( range( 1, 2 ) as $_ ) {
			$topics = $configurationLoader->loadTopics();
			$this->assertInstanceOf( StatusValue::class, $topics );
			$this->assertTrue( $topics->hasMessage( 'foo' ) );
		}
	}

	/**
	 * @covers ::loadTopics
	 * @dataProvider provideLoadMorelikeTopics_error
	 */
	public function testLoadMorelikeTopics_error( $error ) {
		$configurationLoader = $this->getConfigurationLoader( [], $this->getMorelikeTopicConfig( $error ),
			PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE );
		$status = $configurationLoader->loadTopics();
		$this->assertInstanceOf( StatusValue::class, $status );
		$this->assertTrue( $status->hasMessage(
			'growthexperiments-homepage-suggestededits-config-' . $error
		) );
	}

	public function provideLoadMorelikeTopics_error() {
		return [
			[ 'wrongstructure' ],
			[ 'invalidid' ],
			[ 'missingfield' ],
		];
	}

	/**
	 * @covers ::loadTopics
	 */
	public function testLoadTopics_noLoader() {
		$messageLocalizer = $this->getMockMessageLocalizer();
		$taskTitle = new TitleValue( NS_MAIN, 'TaskConfiguration' );
		$pageLoader = $this->getMockPageLoader( [ '0:TaskConfiguration' => [] ] );
		$collation = $this->getMockCollation();
		$configurationLoader = new PageConfigurationLoader( $messageLocalizer, $pageLoader, $collation,
			$taskTitle, null, PageConfigurationLoader::CONFIGURATION_TYPE_ORES );
		$topics = $configurationLoader->loadTopics();
		$this->assertSame( [], $topics );
	}

	/**
	 * @covers ::loadTemplateBlacklist
	 */
	public function testLoadTemplateBlacklist() {
		$this->markTestSkipped( 'Not implemented yet' );
	}

	/**
	 * Test configuration
	 * @param string $error
	 * @return array|int
	 */
	protected function getTaskConfig( $error = null ) {
		$config = [
			'copyedit' => [
				'icon' => 'articleCheck',
				'group' => 'easy',
				'templates' => [ 'Foo', 'Bar', 'Baz' ],
			],
			'references' => [
				'icon' => 'references',
				'group' => 'medium',
				'templates' => [ 'R1', 'R2', 'R3' ],
			],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			return [ '*' => [] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['references']['group'] );
		} elseif ( $error === 'wronggroup' ) {
			$config['references']['group'] = 'hardest';
		} elseif ( $error === 'missingmessage' ) {
			$config['foo'] = [ 'icon' => 'foo', 'group' => 'hard', 'templates' => [ 'T' ] ];
		}
		return $config;
	}

	/**
	 * Test configuration
	 * @param string $error
	 * @return array|int
	 */
	protected function getOresTopicConfig( $error = null ) {
		$config = [
			'topics' => [
				'art' => [
					'group' => 'culture',
					'oresTopics' => [ 'music', 'painting' ],
				],
				'science' => [
					'group' => 'stem',
					'oresTopics' => [ 'physics', 'biology' ],
				],
				'food' => [
					'group' => 'culture',
					'oresTopics' => [ 'food' ],
				],
			],
			'groups' => [ 'culture', 'stem' ],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			$config['topics']['*'] = [ 'group' => 'test', 'oresTopics' => [ 'test' ] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['topics']['science']['group'] );
		}
		return $config;
	}

	/**
	 * Test configuration
	 * @param string $error
	 * @return array|int
	 */
	protected function getMorelikeTopicConfig( $error = null ) {
		$config = [
			'art' => [
				'label' => 'Art',
				'titles' => [ 'Music', 'Painting' ],
			],
			'science' => [
				'label' => 'Science',
				'titles' => [ 'Physics', 'Biology' ],
			],
		];
		if ( $error === 'wrongstructure' ) {
			return 0;
		} elseif ( $error === 'invalidid' ) {
			return [ '*' => [] ];
		} elseif ( $error === 'missingfield' ) {
			unset( $config['science']['titles'] );
		}
		return $config;
	}

	/**
	 * @param array|StatusValue $taskConfig
	 * @param array|StatusValue $topicConfig
	 * @param string $topicType
	 * @param Message[] $customMessages
	 * @return PageConfigurationLoader
	 */
	protected function getConfigurationLoader(
		$taskConfig, $topicConfig, $topicType, array $customMessages = []
	) {
		$taskConfigTitle = new TitleValue( NS_MAIN, 'TaskConfigPage' );
		$topicConfigTitle = new TitleValue( NS_MAIN, 'TopicConfigPage' );
		$messageLocalizer = $this->getMockMessageLocalizer( $customMessages );
		$pageLoader = $this->getMockPageLoader( [
			'0:TaskConfigPage' => $taskConfig,
			'0:TopicConfigPage' => $topicConfig,
		] );
		$collation = $this->getMockCollation();
		return new PageConfigurationLoader( $messageLocalizer, $pageLoader, $collation,
			$taskConfigTitle, $topicConfigTitle, $topicType );
	}

	/**
	 * @param Message[] $customMessages
	 * @return MessageLocalizer|MockObject
	 */
	protected function getMockMessageLocalizer( array $customMessages = [] ) {
		$localizer = $this->getMockBuilder( MessageLocalizer::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$localizer->method( 'msg' )
			->willReturnCallback( function ( $key, ...$params ) use ( $customMessages ) {
				if ( isset( $customMessages[$key] ) ) {
					return $customMessages[$key];
				}
				return $this->getMockMessage( $key, ...$params );
			} );
		return $localizer;
	}

	/**
	 * @param array $map Map of title => JSON array or StatusValue, where title is in
	 *   stringified TitleValue format.
	 * @return PageLoader|MockObject
	 */
	protected function getMockPageLoader( $map ) {
		$loader = $this->getMockBuilder( PageLoader::class )
			->disableOriginalConstructor()
			->setMethods( [ 'load' ] )
			->getMock();
		$loader->expects( $this->atMost( 1 ) )
			->method( 'load' )
			->willReturnCallback( function ( LinkTarget $configPage ) use ( $map ) {
				$title = $configPage->getNamespace() . ':' . $configPage->getDBkey();
				$this->assertArrayHasKey( $title, $map );
				return $map[$title];
			} );
		return $loader;
	}

	/**
	 * @param Message[] $customMessages
	 * @return IContextSource|MockObject
	 */
	protected function getMockContext( array $customMessages = [] ) {
		$context = $this->getMockBuilder( IContextSource::class )
			->setMethods( [ 'msg' ] )
			->getMockForAbstractClass();
		$context->method( 'msg' )->willReturnCallback( function ( $key ) use ( $customMessages ) {
			if ( isset( $customMessages[$key] ) ) {
				return $customMessages[$key];
			}
			return $this->getMockMessage( $key );
		} );
		return $context;
	}

	/**
	 * @return Collation|MockObject
	 */
	protected function getMockCollation() {
		$collation = $this->getMockBuilder( Collation::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getSortKey' ] )
			->getMockForAbstractClass();
		$collation->method( 'getSortKey' )->willReturnArgument( 0 );
		return $collation;
	}

}
