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

		YoastSEO.app.registerTest( 'productTitle', testProductDescription, testProductDescriptionScore, 'YoastVideoSEO' );
	}

	/**
	 *
	 * @type {{scoreArray: *[]}}
	 */
	var testProductDescriptionScore = {
		scoreArray: [
			{
				max: 0,
				score: 1,
				text: ''
			},
			{
				min: 1,
				max: 20,
				score: 5,
				text: ''
			},
			{
				min: 20,
				max: 50,
				score: 9,
				text: ''
			},{
				min: 50,
				score: 5,
				text: ''
			}
		]
	};

	var testProductDescription = function(){
		var productDescription = document.getElementById( 'excerpt' ).value;
		if (typeof tinyMCE !== 'undefined' ){
			productDescription = tinyMCE.get( 'excerpt').getContent();
		}
		productDescription = YoastSEO.getStringHelper().stripAllTags( productDescription );
		return productDescription.split( ' ' );
	}

}());
