( function () {
	var Help = require( 'ext.growthExperiments.Help' ),
		Utils = require( '../utils/ext.growthExperiments.Utils.js' ),
		taskTypes = require( './TaskTypes.json' ),
		HelpPanelLogger = Help.HelpPanelLogger,
		HelpPanelProcessDialog = Help.HelpPanelProcessDialog,
		mobileFrontend = mw.mobileFrontend,
		Drawer = mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Drawer : undefined,
		Anchor = mobileFrontend ? mw.mobileFrontend.require( 'mobile.startup' ).Anchor : undefined,
		configData = Help.configData,
		suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance(),
		suggestedEditsPeek = require( './../helppanel/ext.growthExperiments.SuggestedEditsPeek.js' ),
		guidanceEnabled = mw.config.get( 'wgGENewcomerTasksGuidanceEnabled' ),
		pageMode = 'read',
		guidanceAvailable,
		taskTypeId,
		mobilePeek;

	if ( guidanceEnabled && suggestedEditSession.active &&
		!suggestedEditSession.postEditDialogNeedsToBeShown
	) {
		require( './../homepage/suggestededits/ext.growthExperiments.SuggestedEdits.Guidance.js' );
	}
	taskTypeId = suggestedEditSession.taskType;
	guidanceAvailable = guidanceEnabled && taskTypeId && taskTypes[ taskTypeId ];

	// This shouldn't happen, but just to be sure
	if ( !mw.config.get( 'wgGEHelpPanelEnabled' ) ) {
		return;
	}

	$( function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		var $buttonToInfuse = $( '#mw-ge-help-panel-cta-button' ),
			$buttonWrapper = $buttonToInfuse.parent(),
			$mfOverlay,
			$veUiOverlay,
			// eslint-disable-next-line no-jquery/no-global-selector
			$body = $( 'body' ),
			windowManager = new OO.ui.WindowManager( { modal: OO.ui.isMobile() } ),
			$overlay = $( '<div>' ).addClass( 'mw-ge-help-panel-widget-overlay' ),
			logger = new HelpPanelLogger( configData.GEHelpPanelLoggingEnabled, {
				previousEditorInterface: suggestedEditSession.editorInterface,
				// If the user is following a link in a suggested edit task card (which has a click ID,
				// which causes wgGEHomepagePageviewToken to be set on the server side), inherit
				// the session ID of the source page. Otherwise, preserve the suggested edit session's
				// ID, if we are in one.
				sessionId: mw.config.get( 'wgGEHomepagePageviewToken' ) ||
					suggestedEditSession.active && suggestedEditSession.clickId,
				isSuggestedTask: suggestedEditSession.active
			} ),
			size = OO.ui.isMobile() ? 'full' : 'small',
			/**
			 * @type {OO.ui.Window}
			 */
			helpPanelProcessDialog = new HelpPanelProcessDialog( {
				// Make help panel wider for larger screens.
				size: Math.max( document.documentElement.clientWidth, window.innerWidth || 0 ) > 1366 ? 'medium' : size,
				logger: logger,
				guidanceEnabled: guidanceEnabled,
				taskTypeId: taskTypeId
			} ),
			helpCtaButton,
			lifecycle;

		/**
		 * Invoked from mobileFrontend.editorOpened, ve.activationComplete
		 * and wikipage.editform hooks.
		 *
		 * The CTA needs to be (re-)attached to the overlay when VisualEditor or
		 * the MobileFrontend editor is opened.
		 *
		 * @param {string} editor Which editor is being opened
		 */
		function attachHelpButton( editor ) {
			var metadataOverride = {};
			if ( Utils.isValidEditor( editor ) ) {
				/* eslint-disable-next-line camelcase */
				metadataOverride.editor_interface = editor;
			}
			// Don't reattach the button wrapper if it's already attached to the overlay, otherwise
			// the animation happens twice
			if ( $buttonWrapper.parent()[ 0 ] !== $overlay[ 0 ] ) {
				$overlay.append( $buttonWrapper );
			}
			pageMode = 'edit';
			if ( helpPanelProcessDialog.isOpened() ) {
				helpPanelProcessDialog.setEditMode();
			}
			logger.log( 'impression', null, metadataOverride );
		}

		/**
		 * Invoked from mobileFrontend.editorClosed and ve.deactivationComplete hooks.
		 *
		 * mobileFrontend.editorClosed is fired both during editor switching and
		 * when closing an editor to go back to reading mode.
		 *
		 * Hide the CTA when VisualEditor or the MobileFrontend editor is closed.
		 */
		function detachHelpButton() {
			windowManager.closeWindow( helpPanelProcessDialog );
			// If the help panel should show for the namespace, then don't detach the button
			// and also log an impression.
			if ( configData.GEHelpPanelReadingModeNamespaces.indexOf( mw.config.get( 'wgNamespaceNumber' ) ) !== -1 ) {
				// When closing the wikitext editor, the url is only updated some time after
				// so there is a chance that we need to log an impression event but we'll
				// only know for sure a little later ;)
				setTimeout( function () {
					if ( logger.getEditor() === 'reading' ) {
						logger.log( 'impression' );
					}
				}, 250 );
				return;
			}
			$buttonWrapper.detach();
			pageMode = 'read';
		}

		if ( $buttonToInfuse.length ) {
			helpCtaButton = OO.ui.ButtonWidget.static.infuse( $buttonToInfuse );
		} else {
			helpCtaButton = new OO.ui.ButtonWidget( {
				id: 'mw-ge-help-panel-cta-button',
				href: mw.util.getUrl( configData.GEHelpPanelHelpDeskTitle ),
				label: OO.ui.isMobile() ? '' : mw.msg( 'growthexperiments-help-panel-cta-button-text' ),
				icon: 'help',
				flags: [ 'primary', 'progressive' ]
			} );
			$buttonWrapper = $( '<div>' )
				.addClass( 'mw-ge-help-panel-cta' )
				.append( helpCtaButton.$element );
			if ( OO.ui.isMobile() ) {
				$buttonWrapper.addClass( 'mw-ge-help-panel-cta-mobile' );
			}
		}
		$buttonWrapper.addClass( 'mw-ge-help-panel-ready' );

		$overlay.append( windowManager.$element );
		if ( !OO.ui.isMobile() ) {
			$overlay.addClass( 'mw-ge-help-panel-popup' );
		}
		$body.append( $overlay );
		windowManager.addWindows( [ helpPanelProcessDialog ] );

		function openHelpPanel( panel ) {
			if ( OO.ui.isMobile() ) {
				// HACK: Detach the MobileFrontend overlay for both VE and source edit modes.
				// Per T212967, leaving them enabled results in a phantom text input that the
				// user can only see the cursor input for.
				// eslint-disable-next-line no-jquery/no-global-selector
				$mfOverlay = $( '.overlay' ).detach();
				// Detach the VE UI overlay, needed to prevent interference with scrolling in
				// our dialog.
				// eslint-disable-next-line no-jquery/no-global-selector
				$veUiOverlay = $( '.ve-ui-overlay' ).detach();
				// More hacks. WindowManager#toggleGlobalEvents adds the modal-active class,
				// which is styled with position:relative. This seems to interfere with search
				// results scroll on iOS.
				// Unlike the overlays which we detach above and re-append when the lifecycle
				// is closing, we do not need to re-add the class to the body since WindowManager
				// removes the class on closing.
				$body.removeClass( 'oo-ui-windowManager-modal-active' );
			}
			lifecycle = windowManager.openWindow( helpPanelProcessDialog, {
				panel: panel,
				pageMode: pageMode
			} );
			helpCtaButton.toggle( false );
			logger.log( 'open' );
			lifecycle.closing.done( function () {
				// Re-attach the MobileFrontend and VE overlays on mobile.
				if ( OO.ui.isMobile() ) {
					$body.append( $mfOverlay );
					$body.append( $veUiOverlay );
				}
				helpCtaButton.toggle( true );
			} );
			return lifecycle;
		}

		function addMobilePeek( taskTypeData ) {
			mobilePeek = new Drawer( {
				className: 'suggested-edits-mobile-peek',
				showCollapseIcon: false,
				children: [
					suggestedEditsPeek.getSuggestedEditsPeek(
						'suggested-edits-mobile-peek-content',
						taskTypeData.messages,
						taskTypeData.difficulty
					),
					$( '<div>' ).addClass( 'suggested-edits-mobile-peek-footer' )
						.append(
							new Anchor( {
								href: '#',
								additionalClassNames: 'suggested-edits-mobile-peek-more-about-this-edit',
								progressive: true,
								label: mw.msg( 'growthexperiments-homepage-suggestededits-mobile-peek-more-about-this-edit' )
							} ).$el
						)
				],
				onBeforeHide: function ( drawer ) {
					setTimeout( function () {
						helpCtaButton.toggle( true );
						drawer.$el.remove();
					}, 250 );
				}
			} );
			mobilePeek.$el.find( '.suggested-edits-mobile-peek' ).on( 'click', function () {
				mobilePeek.hide();
				openHelpPanel( 'suggested-edits' );
			} );
			document.body.appendChild( mobilePeek.$el[ 0 ] );
			helpCtaButton.toggle( false );
			mobilePeek.show();
		}

		if ( guidanceAvailable ) {
			if ( OO.ui.isMobile() ) {
				addMobilePeek( taskTypes[ taskTypeId ] );
			} else {
				// Open the help panel to the suggested-edits panel, animating it in from the bottom
				// Perform this special animation only once, the first time the help panel opens
				$overlay.addClass( 'mw-ge-help-panel-popup-guidance' );
				openHelpPanel( 'suggested-edits' ).closing.done( function () {
					$overlay.removeClass( 'mw-ge-help-panel-popup-guidance' );
				} );
			}
		}

		helpCtaButton.on( 'click', function () {
			openHelpPanel( guidanceAvailable && pageMode === 'read' ? 'suggested-edits' : 'home' );
		} );

		// Attach or detach the help panel CTA in response to hooks from MobileFrontend.
		if ( OO.ui.isMobile() ) {
			mw.hook( 'mobileFrontend.editorOpened' ).add( attachHelpButton );
			mw.hook( 'mobileFrontend.editorClosed' ).add( detachHelpButton );
		} else {
			// VisualEditor activation hooks are ignored in mobile context because MobileFrontend
			// hooks are sufficient for attaching/detaching the help CTA.
			mw.hook( 've.activationComplete' ).add( attachHelpButton );
			mw.hook( 've.deactivationComplete' ).add( detachHelpButton );

			// Older wikitext editor
			mw.hook( 'wikipage.editform' ).add( attachHelpButton );
		}

		// If viewing an article, log the impression. Editing impressions are
		// logged via attachHelpButton(), but we don't need to utilize that
		// function on view.
		if ( logger.getContext() === 'reading' ) {
			logger.log( 'impression' );
		}
	} );

}() );
