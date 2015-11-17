(function() {
	/**
	 * Adds eventlistener to load the Yoast WooCommerce plugin
	 */
	jQuery( window ).on( 'YoastSEO:ready', function() {
		new YoastWooCommercePlugin();
	});

	/**
	 * Registers Plugin and Test for Yoast WooCommerce.
	 */
	function YoastWooCommercePlugin() {
		YoastSEO.app.registerPlugin( 'YoastWooCommerce', { 'status': 'ready' } );

		YoastSEO.app.registerTest( 'productTitle', this.productDescription, productDescriptionScore, 'YoastWooCommerce' );

		this.addCallback();

		YoastSEO.app.registerPlugin( 'YoastWooCommercePlugin', { status: 'ready' } );

		this.registerModifications();

		this.bindEvents();
	}

	/**
	 * Scoring array for the product descriptions. Takes texts from the localize script in wpseo-commerce.php.
	 * @type {{scoreArray: *[]}}
	 */
	var productDescriptionScore = {
		scoreArray: [
			{
				max: 0,
				score: 1,
				text: wpseoWooL10n.woo_desc_none
			},
			{
				min: 1,
				max: 20,
				score: 5,
				text: wpseoWooL10n.woo_desc_short
			},
			{
				min: 20,
				max: 50,
				score: 9,
				text: wpseoWooL10n.woo_desc_good
			},{
				min: 50,
				score: 5,
				text: wpseoWooL10n.woo_desc_long
			}
		]
	};

	/**
	 * Tests the length of the productdescription.
	 * @returns {Number}
	 */
	YoastWooCommercePlugin.prototype.productDescription = function(){
		var productDescription = document.getElementById( 'excerpt' ).value;
		if (typeof tinyMCE !== 'undefined' && tinyMCE.get( 'excerpt') !== null) {
			productDescription = tinyMCE.get( 'excerpt').getContent();
		}
		productDescription = YoastSEO.getStringHelper().stripAllTags( productDescription );
		return productDescription.split( ' ' ).length;
	};

	/**
	 * Adds callback to the excerpt field to trigger the analyzeTimer when product description is updated.
	 * The tinyMCE triggers automatically since that inherets the binding from the content field tinyMCE.
	 */
	YoastWooCommercePlugin.prototype.addCallback = function() {
		var elem = document.getElementById( 'excerpt' );
		if( elem !== null ){
			elem.addEventListener( 'input', YoastSEO.app.analyzeTimer.bind( YoastSEO.app ) );
		}

	};

	YoastWooCommercePlugin.prototype.bindEvents = function() {
		jQuery( '.add_product_images' ).find( 'a' ).on( 'click', this.bindLinkEvent.bind( this ) );

	};

	var buttonEventCounter = 0;
	var deleteEventCounter = 0;

	YoastWooCommercePlugin.prototype.bindLinkEvent = function() {
		if (jQuery( '.media-modal-content' ).find( '.media-button' ).length === 0 ) {
			buttonEventCounter++;
			if ( buttonEventCounter < 10 ) {
				setTimeout( this.bindLinkEvent.bind( this ) );
			}
		} else {
			buttonEventCounter = 0;
			this.bindButtonEvent();

		}
	};

	YoastWooCommercePlugin.prototype.bindButtonEvent = function() {
		jQuery( '.media-modal-content' ).find( '.media-button' ).on( 'click', this.buttonCallback.bind( this )  );

	};

	YoastWooCommercePlugin.prototype.buttonCallback = function() {
		//YoastSEO.app.analyzeTimer.bind( YoastSEO.app );
		YoastSEO.app.analyzeTimer();
		this.bindDeleteEvent();
	};

	YoastWooCommercePlugin.prototype.bindDeleteEvent = function() {
		if ( jQuery( '#product_images_container' ).find( '.delete' ).length === 0 ){
			deleteEventCounter++;
			if ( deleteEventCounter < 10 ) {
				setTimeout( this.bindDeleteEvent.bind( this ) );
			}
		} else {
			deleteEventCounter = 0;
			jQuery( '#product_images_container' ).find( '.delete' ).on( 'click', YoastSEO.app.analyzeTimer.bind( YoastSEO.app ) );
		}
	};

	/**
	 * Registers the addImageToContent modification
	 */
	YoastWooCommercePlugin.prototype.registerModifications = function() {
		var callback = this.addImageToContent.bind( this );

		YoastSEO.app.registerModification( 'content', callback, 'YoastWooCommercePlugin', 10 );
	};

	/**
	 * Adds the images from the pagegallery to the content to be analyzed by the analyzer.
	 * @param data {String}
	 * @returns {String}
	 */
	YoastWooCommercePlugin.prototype.addImageToContent = function( data ) {
		var images = jQuery( '#product_images_container' ).find( 'img' );

		for (var i = 0; i < images.length; i++ ){
			data += images[ i ].outerHTML;
		}
		return data;
	};
}
());
