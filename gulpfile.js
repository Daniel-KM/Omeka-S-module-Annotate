'use strict';

const del = require('del');
const gulp = require('gulp');

gulp.task('clean', function(done) {
    return del('asset/vendor/webui-popover');
});

gulp.task('sync', function (done) {
        gulp.src(['node_modules/webui-popover/dist/**'])
        .pipe(gulp.dest('asset/vendor/webui-popover/'))
        .on('end', done);
    }
);

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
