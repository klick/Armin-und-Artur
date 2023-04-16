// Initialize modules
// Importing specific gulp API functions lets us write them below as series() instead of gulp.series()
const { src, dest, watch, series, parallel } = require('gulp');
// Importing all the Gulp-related packages we want to use
const gulp = require('gulp');
const sourcemaps = require('gulp-sourcemaps');
const sass = require('gulp-sass');
const concat = require('gulp-concat');
const uglify = require('gulp-uglify');
const rev = require('gulp-rev');
const revDel = require('rev-del');
gutil = require('gulp-util');
//const postcss = require('gulp-postcss');
//const autoprefixer = require('autoprefixer');
const cssnano = require('gulp-cssnano');
//var replace = require('gulp-replace');


// File paths
const files = { 
    scssPath: '_scss/**/*.scss',
    jsPath: '_js/**/*.js'
}

// Sass task: compiles the style.scss file into style.css
function scssTask(){    
    return src(files.scssPath)
    .pipe(sourcemaps.init())  // Process the original sources
    .pipe(sass())
    .pipe(cssnano())
    .pipe(concat('css/styles.css'))
    .pipe(gulp.dest('web/')).on('error', gutil.log) // write manifest to build dir
    .pipe(rev())
    .pipe(sourcemaps.write('_maps')) // Add the map to modified source.
    .pipe(gulp.dest('web/'))
    .pipe(rev.manifest('../web/rev-manifest.json'))
    .pipe(revDel({ dest: 'web/', suppress: false }))
    .pipe(gulp.dest('web'));  // write manifest to build dir
}

// JS task: concatenates and uglifies JS files to script.js
function jsTask(){
    return src([
        files.jsPath
        //,'!' + 'includes/js/jquery.min.js', // to exclude any specific files
        ])
        .pipe(concat('bundle.js'))
        .pipe(uglify())
        .pipe(dest('web/js')
    );
}

// Cachebust
// function cacheBustTask(){
//     var cbString = new Date().getTime();
//     return src(['index.html'])
//         .pipe(replace(/cb=\d+/g, 'cb=' + cbString))
//         .pipe(dest('.'));
// }

// Watch task: watch SCSS and JS files for changes
// If any change, run scss and js tasks simultaneously
function watchTask(){
    watch([files.scssPath, files.jsPath],
        {interval: 1000, usePolling: true}, //Makes docker work
        series(
            parallel(scssTask, jsTask),
            //cacheBustTask
        )
    );    
}

// Export the default Gulp task so it can be run
// Runs the scss and js tasks simultaneously
// then runs cacheBust, then watch task
exports.default = series(
    parallel(scssTask, jsTask), 
    //cacheBustTask,
    watchTask
);