const mix = require('laravel-mix');
const webpack = require('webpack');

mix.setPublicPath('./webroot')
    .js('assets/js/startup.js', 'webroot/js')
    .extract(['bootstrap', 'jquery', 'popper.js'], 'webroot/js/core.js')
    .extract(['@hotwired/turbo'], 'webroot/js/hotwired_turbo.js')
    .sass('assets/sass/app.scss', 'webroot/css')
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
    .version();

