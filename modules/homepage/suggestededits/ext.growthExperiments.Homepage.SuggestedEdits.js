( function () {
	var EditCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEditCardWidget.js' ),
		EndOfQueueWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.EndOfQueueWidget.js' ),
		ErrorCardWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.ErrorCardWidget.js' ),
		NoResultsWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.NoResultsWidget.js' ),
		TaskExplanationWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.TaskExplanationWidget.js' ),
		PagerWidget = require( './ext.growthExperiments.Homepage.SuggestedEditPagerWidget.js' ),
		PreviousNextWidget = require( './ext.growthExperiments.Homepage.SuggestedEditsPreviousNextWidget.js' ),
		FiltersButtonGroupWidget = require( './ext.growthExperiments.Homepage.SuggestedEdits.FiltersWidget.js' ),
		Logger = require( 'ext.growthExperiments.Homepage.Logger' ),
		taskTypes = require( './TaskTypes.json' ),
		aqsConfig = require( './AQSConfig.json' ),
		initialTaskTypes = [ 'copyedit', 'links' ].filter( function ( taskType ) {
			return taskType in taskTypes;
		} );

	/**
	 * @param {Object} config Configuration options
	 * @param {jQuery} config.$element SuggestedEdits widget container
	 * @param {Array<string>} config.taskTypePresets List of IDs of enabled task types
	 * @param {string} config.mode Rendering mode. See constants in HomepageModule.php
	 * @param {HomepageModuleLogger} logger
	 * @constructor
	 */
	function SuggestedEditsModule( config, logger ) {
		var $pager, $previous, $next, $filters;
		SuggestedEditsModule.super.call( this, config );

		this.logger = logger;
		this.mode = config.mode;
		this.currentCard = null;
		this.apiPromise = null;

		this.filters = new FiltersButtonGroupWidget( {
			presets: config.taskTypePresets,
			mode: this.mode
		}, logger )
			.connect( this, { search: 'fetchTasks' } )
			.toggle( false );
		this.pager = new PagerWidget().toggle( false );
		this.previousWidget = new PreviousNextWidget( { direction: 'Previous' } )
			.connect( this, { click: 'onPreviousCard' } )
			.toggle( false );
		this.nextWidget = new PreviousNextWidget( { direction: 'Next' } )
			.connect( this, { click: 'onNextCard' } )
			.toggle( false );

		$pager = this.$element.find( '.suggested-edits-pager' );
		if ( !$pager.length ) {
			$pager = $( '<div>' ).addClass( 'suggested-edits-pager' ).appendTo( this.$element );
		}
		$previous = this.$element.find( '.suggested-edits-previous' );
		if ( !$previous.length ) {
			$previous = $( '<div>' ).addClass( 'suggested-edits-previous' ).appendTo( this.$element );
		}
		$next = this.$element.find( '.suggested-edits-next' );
		if ( !$next.length ) {
			$next = $( '<div>' ).addClass( 'suggested-edits-next' ).appendTo( this.$element );
		}

		$filters = this.$element.find( '.suggested-edits-filters' );
		if ( !$filters.length ) {
			$filters = $( '<div>' ).addClass( 'suggested-edits-filters' ).appendTo( this.$element );
		}

		$pager.append( this.pager.$element );
		$previous.append( this.previousWidget.$element );
		$next.append( this.nextWidget.$element );
		$filters.append( this.filters.$element );
	}

	OO.inheritClass( SuggestedEditsModule, OO.ui.Widget );

	/**
	 * Fetch suggested edits from ApiQueryGrowthTasks.
	 *
	 * @param {string[]} taskTypes
	 * @return {jQuery.Promise}
	 */
	SuggestedEditsModule.prototype.fetchTasks = function ( taskTypes ) {
		var apiParams = {
			action: 'query',
			prop: 'info|revisions|pageimages',
			inprop: 'protection|url',
			rvprop: 'ids',
			pithumbsize: 260,
			generator: 'growthtasks',
			// Fetch more in case protected articles are in the result set, so that after
			// filtering we can have 200.
			// TODO: Filter out protected articles on the server side.
			ggtlimit: 250,
			ggttasktypes: taskTypes.join( '|' ),
			formatversion: 2,
			uselang: mw.config.get( 'wgUserLanguage' )
		};
		if ( this.apiPromise ) {
			this.apiPromise.abort();
		}
		this.currentCard = null;
		this.taskQueue = [];
		this.queuePosition = 0;
		if ( !taskTypes.length ) {
			// User has deselected all checkboxes; update the count and show
			// no results.
			this.filters.updateMatchCount( this.taskQueue.length );
			return this.showCard( new NoResultsWidget( { topicMatching: false } ) );
		}
		this.filters.updateButtonLabelAndIcon( taskTypes );
		this.apiPromise = new mw.Api().get( apiParams );
		return this.apiPromise.then( function ( data ) {
			// HomepageModuleLogger adds this to the log data automatically
			var extraData = mw.config.get( 'wgGEHomepageModuleActionData-suggested-edits' );
			function cleanUpData( item ) {
				return {
					title: item.title,
					// Page and revision ID can be missing on development setups where the
					// returned titles are not local, and also in edge cases where the search
					// index has not caught up with a page deletion yet.
					pageId: item.pageid || null,
					revisionId: item.revisions ? item.revisions[ 0 ].revid : null,
					url: item.canonicalurl,
					thumbnailSource: item.thumbnail && item.thumbnail.source || null,
					tasktype: item.tasktype,
					difficulty: item.difficulty,
					maintenanceTemplates: item.maintenancetemplates || null
				};
			}
			function filterOutProtectedArticles( result ) {
				return result.protection.length === 0;
			}
			if ( data.growthtasks.totalCount > 0 ) {
				this.taskQueue = data.query.pages
					.filter( filterOutProtectedArticles )
					.sort( function ( l, r ) {
						return l.order - r.order;
					} )
					.map( cleanUpData )
					// Maximum number of tasks in the queue is always 200.
					.slice( 0, 200 );
			}
			this.filters.updateMatchCount( this.taskQueue.length );
			extraData.taskTypes = taskTypes;
			// FIXME should this be capped to 200 or show the total server-side result count?
			extraData.taskCount = this.taskQueue.length;
			this.logger.log( 'suggested-edits', this.mode, 'se-fetch-tasks' );
			// use done instead of then so failed preloads will be retried when the
			// user navigates
			return this.showCard().done( function () {
				this.preloadNextCard();
			}.bind( this ) );
		}.bind( this ) ).catch( function ( error, details ) {
			if ( error === 'http' && details && details.textStatus === 'abort' ) {
				// Don't show error card for XHR abort.
				return;
			}
			// TODO log more information about the error
			this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
				{ type: 'error' } );
			this.showCard( new ErrorCardWidget() );
		}.bind( this ) );
	};

	SuggestedEditsModule.prototype.updatePager = function () {
		if ( this.taskQueue.length ) {
			this.pager.setMessage( this.queuePosition + 1, this.taskQueue.length );
			this.pager.toggle( true );
		} else {
			this.pager.toggle( false );
		}
	};

	SuggestedEditsModule.prototype.updatePreviousNextButtons = function () {
		var hasPrevious = this.queuePosition > 0,
			hasNext = this.queuePosition < this.taskQueue.length;
		this.previousWidget.setDisabled( !hasPrevious );
		this.nextWidget.setDisabled( !hasNext );
		this.previousWidget.toggle( this.taskQueue.length );
		this.nextWidget.toggle( this.taskQueue.length );
	};

	SuggestedEditsModule.prototype.preloadNextCard = function () {
		if ( this.taskQueue[ this.queuePosition + 1 ] &&
			!this.taskQueue[ this.queuePosition + 1 ].extract
		) {
			this.getExtraDataAndUpdateQueue( this.queuePosition + 1 );
		}
	};

	SuggestedEditsModule.prototype.onNextCard = function () {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-navigation', { dir: 'next' } );
		this.queuePosition = this.queuePosition + 1;
		this.showCard();
		this.preloadNextCard();
	};

	SuggestedEditsModule.prototype.onPreviousCard = function () {
		this.logger.log( 'suggested-edits', this.mode, 'se-task-navigation', { dir: 'prev' } );
		this.queuePosition = this.queuePosition - 1;
		this.showCard();
	};

	SuggestedEditsModule.prototype.updateTaskExplanationWidget = function () {
		var explanationSelector = '.suggested-edits-task-explanation',
			$explanationElement = $( explanationSelector );
		if ( this.queuePosition < this.taskQueue.length ) {
			$explanationElement.html(
				new TaskExplanationWidget( {
					taskType: this.taskQueue[ this.queuePosition ].tasktype,
					mode: this.mode
				}, this.logger ).$element
			);
			$explanationElement.toggle( true );
		} else {
			$explanationElement.toggle( false );
		}
	};

	/**
	 * Extract log data for use by HomepageModuleLogger.log in card impression and click events.
	 * @param {int} cardPosition Card position in the task queue. Assumes summary data has
	 *   already been loaded for this position.
	 * @return {Object<string>}
	 */
	SuggestedEditsModule.prototype.getCardLogData = function ( cardPosition ) {
		var suggestedEditData = this.taskQueue[ cardPosition ];
		return {
			taskType: suggestedEditData.tasktype,
			maintenanceTemplates: suggestedEditData.maintenanceTemplates,
			hasImage: !!suggestedEditData.thumbnailSource,
			ordinalPosition: cardPosition,
			pageviews: suggestedEditData.pageviews,
			pageTitle: suggestedEditData.title,
			pageId: suggestedEditData.pageId,
			revisionId: suggestedEditData.revisionId
			// the page token is automatically added by the logger
		};
	};

	SuggestedEditsModule.prototype.showCard = function ( card ) {
		var queuePosition = this.queuePosition;
		this.currentCard = null;

		// TODO should we log something on non-card impressions?
		if ( card ) {
			this.currentCard = card;
		} else if ( !this.taskQueue.length ) {
			this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
				{ type: 'empty' } );
			this.currentCard = new NoResultsWidget( { topicMatching: false } );
		} else if ( !this.taskQueue[ queuePosition ] ) {
			this.logger.log( 'suggested-edits', this.mode, 'se-task-pseudo-impression',
				{ type: 'end' } );
			this.currentCard = new EndOfQueueWidget( { topicMatching: false } );
		}
		if ( this.currentCard ) {
			this.updateCardAndControlsPresentation();
			return $.Deferred().resolve();
		}

		return this.getExtraDataAndUpdateQueue( queuePosition ).done( function () {
			if ( queuePosition !== this.queuePosition ) {
				return;
			}
			this.logger.log( 'suggested-edits', this.mode, 'se-task-impression',
				this.getCardLogData( queuePosition ) );
			this.currentCard = new EditCardWidget( this.taskQueue[ queuePosition ] );
			this.updateCardAndControlsPresentation();
			this.setupClickLogging();
		}.bind( this ) );
	};

	/**
	 * Gets data which is not reliably available via the action API (we use a nondeterministic
	 * generator so we cannot do query continuation, plus we reorder the results so performance
	 * would be unpredictable). Specifically, lead section extracts from the Page Content Service
	 * summary API and pageviews from the Analytics Query Service.
	 * The PCS endpoint can be customized via $wgGERestbaseUrl (by default will be assumed to be
	 * local; setting it to null will disable text extracts), the AQS endpoint via
	 * $wgPageViewInfoWikimediaDomain (by default will use the Wikimedia instance).
	 * @param {int} taskQueuePosition
	 * @return {Promise} Promise reflecting the status of the PCS request (AQS errors are ignored).
	 */
	SuggestedEditsModule.prototype.getExtraDataAndUpdateQueue = function ( taskQueuePosition ) {
		var pcsPromise, aqsPromise,
			suggestedEditData = this.taskQueue[ taskQueuePosition ];
		if ( !suggestedEditData ) {
			return $.Deferred().resolve().promise();
		}

		pcsPromise = ( 'extract' in suggestedEditData ) ?
			$.Deferred().resolve( {} ).promise() :
			this.getExtraDataFromPcs( suggestedEditData.title );
		aqsPromise = ( 'pageviews' in suggestedEditData ) ?
			$.Deferred().resolve( {} ).promise() :
			this.getExtraDataFromAqs( suggestedEditData.title );
		return $.when( pcsPromise, aqsPromise ).done( function ( pcsData, aqsData ) {
			// If the data is already loaded, xxxData will be an empty {}, so
			// we need to be careful never to override real fields with missing ones.
			if ( pcsData.extract ) {
				suggestedEditData.extract = pcsData.extract;
			}
			// Normally we use the thumbnail source from the action API, this is only a fallback.
			// It is used for some beta wiki configurations and local setups, and also when the
			// action API data is missing due to query+pageimages having a smaller max limit than
			// query+growthtasks.
			if ( !suggestedEditData.thumbnailSource && pcsData.thumbnailSource ) {
				suggestedEditData.thumbnailSource = pcsData.thumbnailSource;
			}
			// AQS never returns data with a pageview total of 0, it just errors out if there are no
			// views. Even if it did, it would probably be better not to show 0 to the user.
			if ( aqsData.pageviews ) {
				suggestedEditData.pageviews = aqsData.pageviews;
			}
			// Update the suggested edit data so we don't need to fetch it again
			// if the user views the card more than once.
			this.taskQueue[ taskQueuePosition ] = suggestedEditData;
		}.bind( this ) );
	};

	/**
	 * Get extracts and page images from PCS.
	 * @param {string} title
	 * @return {Promise<Object>}
	 * @see ::getExtraDataAndUpdateQueue
	 */
	SuggestedEditsModule.prototype.getExtraDataFromPcs = function ( title ) {
		var encodedTitle,
			apiUrlBase = mw.config.get( 'wgGERestbaseUrl' );

		if ( !apiUrlBase ) {
			// Don't fail worse then we have to when RESTBase is not installed.
			return $.Deferred.resolve( '' ).promise();
		}
		encodedTitle = encodeURIComponent( title.replace( / /g, '_' ) );
		return $.get( apiUrlBase + '/page/summary/' + encodedTitle ).then( function ( data ) {
			var pcsData = {};
			pcsData.extract = data.extract;
			if ( data.thumbnail ) {
				pcsData.thumbnailSource = data.thumbnail.source;
			}
			return pcsData;
		} );
	};

	/**
	 * Get pageview data from AQS.
	 * @param {string} title
	 * @return {Promise<int|null>}
	 * @see ::getExtraDataAndUpdateQueue
	 */
	SuggestedEditsModule.prototype.getExtraDataFromAqs = function ( title ) {
		var encodedTitle, pageviewsApiUrl, day, firstPageviewDay, lastPageviewDay;

		encodedTitle = encodeURIComponent( title.replace( / /g, '_' ) );
		// Get YYYYMMDD timestamps of 2 days ago (typically the last day that has full
		// data in AQS) and 60+2 days ago, using Javascript's somewhat cumbersome date API
		day = new Date();
		day.setDate( day.getDate() - 2 );
		lastPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		day.setDate( day.getDate() - 60 );
		firstPageviewDay = day.toISOString().replace( /-/g, '' ).split( 'T' )[ 0 ];
		pageviewsApiUrl = 'https://wikimedia.org/api/rest_v1/metrics/pageviews/per-article/' +
			aqsConfig.project + '/all-access/user/' + encodedTitle + '/daily/' +
			firstPageviewDay + '/' + lastPageviewDay;

		return $.get( pageviewsApiUrl ).then( function ( data ) {
			var pageviews = 0;
			( data.items || [] ).forEach( function ( item ) {
				pageviews += item.views;
			} );
			return pageviews ? { pageviews: pageviews } : {};
		}, function () {
			// AQS returns a 404 when the page has 0 view. Even for real errors, it's
			// not worth replacing the task card with an error message just because we
			// could not put a pageview count on it.
			return {};
		} );
	};

	SuggestedEditsModule.prototype.updateCardAndControlsPresentation = function () {
		var cardSelector = '.suggested-edits-card',
			$cardElement = $( cardSelector );
		$cardElement.html( this.currentCard.$element );
		this.filters.toggle( true );
		this.updatePager();
		this.updatePreviousNextButtons();
		this.updateTaskExplanationWidget();
	};

	/**
	 * Log click events on the task card (ie. the user visiting the task page) and pass
	 * tracking data so events on the task page can be connected.
	 * this.currentCard is expected to contain a valid EditCardWidget.
	 */
	SuggestedEditsModule.prototype.setupClickLogging = function () {
		var $link = this.currentCard.$element.find( '.se-card-content' ),
			clickId = mw.config.get( 'wgGEHomepagePageviewToken' ),
			newUrl = new mw.Uri( $link.attr( 'href' ) ).extend( { geclickid: clickId } ).toString();

		$link
			.attr( 'href', newUrl )
			.on( 'click', function () {
				this.logger.log( 'suggested-edits', this.mode, 'se-task-click',
					this.getCardLogData( this.queuePosition ) );
			}.bind( this ) );
	};

	function initSuggestedTasks( $container ) {
		var suggestedEditsModule,
			savedTaskTypeFilters = mw.user.options.get( 'growthexperiments-homepage-se-filters' ),
			taskTypes = savedTaskTypeFilters ? JSON.parse( savedTaskTypeFilters ) : initialTaskTypes,
			$wrapper = $container.find( '.suggested-edits-module-wrapper' ),
			mode = $wrapper.closest( '.growthexperiments-homepage-module' ).data( 'mode' );
		if ( !$wrapper.length ) {
			return;
		}
		suggestedEditsModule = new SuggestedEditsModule(
			{
				$element: $wrapper,
				taskTypePresets: taskTypes,
				mode: mode
			},
			new Logger(
				mw.config.get( 'wgGEHomepageLoggingEnabled' ),
				mw.config.get( 'wgGEHomepagePageviewToken' )
			) );
		suggestedEditsModule.fetchTasks( taskTypes );
	}

	// Try setup for desktop mode and server-side-rendered mobile mode
	// See also the comment in ext.growthExperiments.Homepage.Mentorship.js
	// eslint-disable-next-line no-jquery/no-global-selector
	initSuggestedTasks( $( '.growthexperiments-homepage-container' ) );

	// Try setup for mobile overlay mode
	mw.hook( 'growthExperiments.mobileHomepageOverlayHtmlLoaded' ).add( function ( moduleName, $content ) {
		if ( moduleName === 'suggested-edits' ) {
			initSuggestedTasks( $content );
		}
	} );
}() );
