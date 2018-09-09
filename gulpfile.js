'use strict';

const del = require('del');
const gulp = require('gulp');

const sourceDir = 'node_modules/webui-popover/dist/**';
const destinationDir = 'asset/vendor/webui-popover';

gulp.task('clean', function(done) {
    return del(destinationDir);
});

gulp.task('sync', function (done) {
        gulp.src([sourceDir])
        .pipe(gulp.dest(destinationDir))
        .on('end', done);
    }
);

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
