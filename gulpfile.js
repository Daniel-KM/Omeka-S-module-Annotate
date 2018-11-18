'use strict';

const del = require('del');
const gulp = require('gulp');
const gulpif = require('gulp-if');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');

const bundle = [
    {
        'source': 'node_modules/webui-popover/dist/**',
        'dest': 'asset/vendor/webui-popover',
    },
];

gulp.task('clean', function(done) {
    bundle.forEach(function (module) {
        return del.sync(module.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    bundle.forEach(function (module) {
        gulp.src(module.source)
            .pipe(gulpif(module.rename, rename({suffix:'.min'})))
            .pipe(gulpif(module.uglify, uglify()))
            .pipe(gulp.dest(module.dest));
    });
    done();
});

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
