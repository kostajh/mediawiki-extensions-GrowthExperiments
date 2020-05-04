( function () {

	var Utils = require( '../utils/ext.growthExperiments.Utils.js' );

	/**
	 * Logging helper for the HelpPanel EventLogging schema.
	 * @class mw.libs.ge.HelpPanelLogger
	 * @constructor
	 * @param {boolean} enabled
	 * @param {Object} [config]
	 * @cfg string [context] Allow overriding the context field for all events
	 * @cfg string [editorInterface] Allow overriding the editor_interface field for all events
	 * @cfg string [isSuggestedTask] Allow overriding the is_suggested_task field for all events.
	 *   This must be set for suggested edits, the logger does not try to detect them.
	 * @cfg string [sessionId] Allow overriding the help_panel_session_id field for all events
	 * @see https://meta.wikimedia.org/wiki/Schema:HelpPanel
	 */
	function HelpPanelLogger( enabled, config ) {
		config = config || {};
		this.enabled = enabled;
		this.userEditCount = mw.config.get( 'wgUserEditCount' );
		this.isMobile = OO.ui.isMobile();
		this.previousEditorInterface = '';
		this.context = config.context || null;
		this.editorInterface = config.editorInterface || null;
		this.isSuggestedTask = config.isSuggestedTask || false;
		this.helpPanelSessionId = config.sessionId || mw.user.generateRandomSessionId();
	}

	/**
	 * Log a HelpPanel event.
	 * @param {string} action Value of the action field
	 * @param {Object|Array<string>|string|number|bool} data Value of the action_data field
	 * @param {Object} [metadataOverride] An object with the values of any other fields. Those
	 *   fields are set to some default value if omitted.
	 */
	HelpPanelLogger.prototype.log = function ( action, data, metadataOverride ) {
		var eventData;
		if ( !this.enabled ) {
			return;
		}

		eventData = $.extend(
			{
				action: action,
				/* eslint-disable-next-line camelcase */
				action_data: Utils.serializeActionData( data )
			},
			this.getMetaData(),
			metadataOverride
		);

		// Test/debug using: `mw.trackSubscribe( 'event.HelpPanel', console.log );`
		mw.track(
			'event.HelpPanel',
			eventData
		);

		this.previousEditorInterface = eventData.editor_interface;
	};

	HelpPanelLogger.prototype.getMetaData = function () {
		var defaultEditor = this.getEditor(),
			defaultContext = this.getContext(),
			readingMode = defaultContext !== 'editing';
		/* eslint-disable camelcase */
		return {
			user_id: mw.user.getId(),
			user_editcount: this.userEditCount,
			context: defaultContext,
			editor_interface: defaultEditor,
			is_suggested_task: this.isSuggestedTask,
			is_mobile: this.isMobile,
			page_id: readingMode ? 0 : mw.config.get( 'wgArticleId' ),
			page_title: readingMode ? '' : mw.config.get( 'wgRelevantPageName' ),
			page_ns: mw.config.get( 'wgNamespaceNumber' ),
			user_can_edit: mw.config.get( 'wgIsProbablyEditable' ),
			page_protection: this.getPageRestrictions(),
			session_token: mw.user.sessionId(),
			help_panel_session_id: this.helpPanelSessionId
		};
		/* eslint-enable camelcase */
	};

	HelpPanelLogger.prototype.isValidEditor = function ( editor ) {
		return [
			'wikitext',
			'wikitext-2017',
			'visualeditor',
			'other'
		].indexOf( editor ) >= 0;
	};

	/**
	 * Check whether the user is editing right now.
	 * @internal
	 * @return {boolean}
	 */
	HelpPanelLogger.prototype.isEditing = function () {
		var uri = new mw.Uri();

		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
			// Good enough for now; at some point special editing interfaces like ContentTranslate
			// might need special handling.
			return false;
		} else if ( this.isMobile ) {
			return uri.fragment && uri.fragment.match( /\/editor\/(\d+|all)/ );
		} else {
			return uri.query.veaction || uri.query.action === 'edit' || uri.query.action === 'submit';
		}
	};

	/**
	 * Return what setting the help panel has been invoked in (editing a page or viewing a page;
	 * there are others but those are set via the metadata override mechanism).
	 * @return {string} A value appropriate for the context field of the schema.
	 */
	HelpPanelLogger.prototype.getContext = function () {
		return this.context || ( this.isEditing() ? 'editing' : 'reading' );
	};

	/**
	 * Get a value appropriate for the editor_interface field of the schema
	 * (the current editor, or the previously used editor, or a best guess what the editor
	 * would be).
	 * @return {string}
	 */
	HelpPanelLogger.prototype.getEditor = function () {
		// Manual override
		if ( this.editorInterface ) {
			return this.editorInterface;
		}

		if ( this.isEditing() ) {
			return this.getCurrentEditor();
		} else {
			return this.getPredictedEditor();
		}
	};

	/**
	 * Returns the name of the current editor (in the format used by the editor_interface field
	 * of the schema). Should only be called when that editor is open.
	 * @internal
	 * @return {string}
	 */
	HelpPanelLogger.prototype.getCurrentEditor = function () {
		var veTarget, surface, mode;

		if ( this.isMobile ) {
			// Mobile: wikitext
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( 'textarea#wikitext-editor:visible' ).length ) {
				return 'wikitext';
			}

			// Mobile: VE
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( '.ve-init-mw-mobileArticleTarget:visible' ).length ) {
				return 'visualeditor';
			}
		} else {
			// Desktop: VE in visual or source mode
			veTarget = OO.getProp( window, 've', 'init', 'target' );
			if ( veTarget ) {
				surface = veTarget.getSurface();
				if ( surface ) {
					mode = surface.getMode();
					if ( mode === 'source' ) {
						return 'wikitext-2017';
					}

					if ( mode === 'visual' ) {
						return 'visualeditor';
					}
				}
			}

			// Desktop: old wikitext editor
			// eslint-disable-next-line no-jquery/no-global-selector, no-jquery/no-sizzle
			if ( $( '#wpTextbox1:visible' ).length ) {
				return 'wikitext';
			}
		}

		return 'other';
	};

	/**
	 * Try to guess what editor the user will use in their next editing session (in the format used
	 * by the editor_interface field of the schema).
	 * @internal
	 * @return {string}
	 */
	HelpPanelLogger.prototype.getPredictedEditor = function () {
		// If we know what was used for the previous edit, return that.
		// Otherwise, we don't even try (for now) and just guess VE.
		return this.previousEditorInterface || 'visualeditor';
	};

	HelpPanelLogger.prototype.getPageRestrictions = function () {
		// wgRestrictionCreate, wgRestrictionEdit, wgRestrictionMove
		return [ 'create', 'edit', 'move' ]
			.map( function ( action ) {
				var restrictions = mw.config.get(
					'wgRestriction' +
					action[ 0 ].toUpperCase() +
					action.substr( 1 ).toLowerCase()
				);
				if ( restrictions && restrictions.length ) {
					return action + '=' + restrictions.join( ',' );
				}
			} )
			.filter( function ( r ) {
				return r;
			} )
			.join( ';' );
	};

	HelpPanelLogger.prototype.incrementUserEditCount = function () {
		this.userEditCount++;
	};

	module.exports = HelpPanelLogger;

}() );
