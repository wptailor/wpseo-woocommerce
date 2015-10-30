(function() {
	/**
	 * Adds eventlistener to load the Yoast WooCommerce plugin
	 */
	addEventListener( "load", function() {
		// Wait for YoastSEO to be loaded
		setTimeout(function() {
			addPlugin();
		}, 0);
	});

	/**
	 * Registers Plugin and Test for Yoast WooCommerce.
	 */
	function addPlugin() {
		YoastSEO.app.registerPlugin( 'YoastWooCommerce', { 'status': 'ready' } );

		YoastSEO.app.registerTest( 'productTitle', testProductDescription, testProductDescriptionScore, 'YoastWooCommerce' );

		addCallback();
	}

	/**
	 * Scoring array for the product descriptions. Takes texts from the localize script in wpseo-commerce.php.
	 * @type {{scoreArray: *[]}}
	 */
	var testProductDescriptionScore = {
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
	var testProductDescription = function(){
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
	var addCallback = function() {
		document.getElementById( 'excerpt' ).addEventListener( "input", YoastSEO.app.analyzeTimer.bind( YoastSEO.app ) );
	};
}());


