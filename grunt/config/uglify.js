// https://github.com/gruntjs/grunt-contrib-uglify
module.exports = {
	"woo": {
		options: {
			preserveComments: "some",
			report: "gzip"
		},
		files: {
			"yoastseo-woo-plugin.min.js": [
				"yoastseo-woo-plugin.js"
			]
		}
	}
};
