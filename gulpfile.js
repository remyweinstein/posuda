'use strict';

const gulp       = require('gulp'),
      prefixer   = require('gulp-autoprefixer'),
      uglify     = require('gulp-uglify'),
      sourcemaps = require('gulp-sourcemaps'),
      concat     = require('gulp-concat'),
      sass       = require('gulp-sass')(require('sass')),
      cssmin     = require('gulp-minify-css');
     
const path = {
            build_app: {
                assets: 'cordova/www/app/assets/',
                js: 'cordova/www/app/build/js/',
                js_vendors: 'cordova/www/app/build/js/vendors/',
                css: 'cordova/www/app/build/styles/',
                indx: 'cordova/www/'
            },

            src_app: {
                assets: ['app/assets/*/*', 'app/assets/*.*'],
                vendors: 'app/src/scss/vendors/*.css',
                indx: ['app/php/templates/index.html', 'manifest.json'],
                cons: ['app/src/js/const/cordova.const.js', 'app/src/js/module.main.js', 'app/src/js/libs/*.js']
            },

            build_desktop: {
                js: 'app/build/js/',
                js_vendors: 'app/build/js/vendors/',
                css: 'app/build/styles/'
            },

            src_desktop: {
                js:   'app/src/js/*.js',
                js_vendors: 'app/src/js/vendors/*.js',
                css: 'app/src/scss/styles.scss',
                vendors: ['app/src/scss/vendors/*.css', 'app/src/scss/static_page/*.css'],
                cons: ['app/src/js/const/site.const.js', 'app/src/js/module.main.js', 'app/src/js/libs/*.js']
            },

            clean: './build'
        };

gulp.task('assets:build', function (done) {
    gulp.src(path.src_app.assets)
        .pipe(gulp.dest(path.build_app.assets));

    done();
});

gulp.task('js:build', function (done) {
    gulp.src(path.src_desktop.js)
        .pipe(uglify())
        .pipe(gulp.dest(path.build_desktop.js));
    gulp.src(path.src_desktop.js_vendors)
        .pipe(uglify())
        .pipe(gulp.dest(path.build_desktop.js_vendors));
    gulp.src(path.src_desktop.js)
        .pipe(uglify())
        .pipe(gulp.dest(path.build_app.js));
    gulp.src(path.src_desktop.js_vendors)
        .pipe(uglify())
        .pipe(gulp.dest(path.build_app.js_vendors));
    gulp.src(path.src_app.cons)
        .pipe(uglify())
        .pipe(concat('module.main.js'))
        .pipe(gulp.dest(path.build_app.js));
    gulp.src(path.src_desktop.cons)
        .pipe(uglify())
        .pipe(concat('module.main.js'))
        .pipe(gulp.dest(path.build_desktop.js));

    done();
});

gulp.task('css:build', function (done) {
    gulp.src(path.src_desktop.css)
        .pipe(sass())
        .pipe(prefixer())
        .pipe(cssmin())
        .pipe(gulp.dest(path.build_desktop.css));
    gulp.src(path.src_desktop.css)
        .pipe(sass())
        .pipe(prefixer())
        .pipe(cssmin())
        .pipe(gulp.dest(path.build_app.css));

    done();
});

gulp.task('vendors:build', function (done) {
    gulp.src(path.src_desktop.vendors)
        .pipe(prefixer())
        .pipe(cssmin())
        .pipe(gulp.dest(path.build_desktop.css));
    gulp.src(path.src_app.vendors)
        .pipe(prefixer())
        .pipe(cssmin())
        .pipe(gulp.dest(path.build_app.css));
    gulp.src(path.src_app.indx)
        .pipe(gulp.dest(path.build_app.indx));

    done();
});

gulp.task('clean', function (cb) {
    rimraf(path.clean, cb);
});

gulp.task('build', gulp.series(
    'assets:build',
    'js:build',
    'css:build',
    'vendors:build'
));

gulp.task('default', gulp.series('build'));