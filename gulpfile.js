'use strict';

const del = require('del');
const gulp = require('gulp');

const bundles = [
    {
        'source': 'node_modules/webui-popover/dist/**',
        'dest': 'asset/vendor/webui-popover',
    },
];

gulp.task('clean', function(done) {
    bundles.map(function (bundle) {
        return del(bundle.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    bundles.map(function (bundle) {
        return gulp.src(bundle.source)
            .pipe(gulp.dest(bundle.dest));
    });
    done();
});

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
