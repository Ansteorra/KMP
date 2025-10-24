const mix = require('laravel-mix');
const webpack = require('webpack');
const fs = require('fs');
const path = require('path');

console.log('Current working directory:', process.cwd());


function getJsFilesFromDir(startPath, skiplist, filter, callback) {
    if (!fs.existsSync(startPath)) {
        console.log("Directory not found: ", startPath);
        return;
    }

    const files = fs.readdirSync(startPath);
    files.forEach(file => {
        const filename = path.join(startPath, file);
        if (skiplist.some((skip) => filename.includes(skip))) {
            return;
        }
        //console.log('checking file to mix:', filename); // Log the filename
        const stat = fs.lstatSync(filename);
        if (stat.isDirectory()) {
            getJsFilesFromDir(filename, skipList, filter, callback); // Recursive call
        } else if (filename.endsWith(filter)) {
            callback(filename);
        }
    });
}
const files = []
const skipList = ['node_modules', 'webroot'];
getJsFilesFromDir('./assets/js', skipList, '-controller.js', (filename) => {
    files.push(filename);
});
getJsFilesFromDir('./plugins', skipList, '-controller.js', (filename) => {
    files.push(filename);
});

console.log('Files to mix:', files);
mix.setPublicPath('./webroot')
    .js(files, 'webroot/js/controllers.js')
    .js('assets/js/index.js', 'webroot/js')
    .extract(['bootstrap', 'popper.js', '@hotwired/turbo', '@hotwired/stimulus', '@hotwired/stimulus-webpack-helpers'], 'webroot/js/core.js')
    .webpackConfig({
        devtool: "source-map",
        optimization: {
            runtimeChunk: true
        },
        plugins: [
            new webpack.ProvidePlugin({
                'bootstrap': 'bootstrap',
            }),
        ],
    })
    .css('assets/css/app.css', 'webroot/css')
    .css('assets/css/signin.css', 'webroot/css')
    .css('assets/css/cover.css', 'webroot/css')
    .css('assets/css/dashboard.css', 'webroot/css')
    .css('plugins/Waivers/assets/css/waivers.css', 'webroot/css/waivers.css')
    .css('plugins/Waivers/assets/css/waiver-upload.css', 'webroot/css/waiver-upload.css')
    .version()
    .sourceMaps();

