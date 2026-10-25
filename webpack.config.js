const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Output files are named js/elabftw_picker-<entry>.js (app id = package.json name)
webpackConfig.entry = {
	reference: path.join(__dirname, 'src', 'reference.js'),
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	personalSettings: path.join(__dirname, 'src', 'personalSettings.js'),
}

module.exports = webpackConfig
