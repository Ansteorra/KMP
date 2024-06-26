const mix = require('laravel-mix');
const webpack = require('webpack');

mix.setPublicPath('./webroot')
    .js('assets/js/startup.js', 'webroot/js')
    .extract(['bootstrap', 'jquery', 'popper.js'], 'webroot/js/core.js')
    .extract(['@hotwired/turbo'], 'webroot/js/hotwired_turbo.js')
    .webpackConfig({
        plugins: [
            new webpack.ProvidePlugin({
                $: 'jquery',
                jQuery: 'jquery',
                'window.jQuery': 'jquery',
                KMP_utils: 'KMP_utils',
                'bootstrap': 'bootstrap',
            }),
        ],
    })
    .css('assets/css/app.css', 'webroot/css')
    .css('assets/css/signin.css', 'webroot/css')
    .css('assets/css/cover.css', 'webroot/css')
    .css('assets/css/dashboard.css', 'webroot/css')
    .version();

