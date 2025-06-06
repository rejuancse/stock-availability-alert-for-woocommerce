/**
 * Gulpfile
 *
 * Implements:
 *      1. CSS: Sass to CSS conversion, error catching, Autoprefixing, Sourcemaps,
 *         CSS minification, and Merge Media Queries.
 *      2. JS: Concatenates & uglifies JS files.
 *      3. Images: Minifies PNG, JPEG, GIF and SVG images.
 *      4. Watches files for changes in CSS or JS.
 *      5. Watches files for changes in PHP.
 *      6. Corrects the line endings.
 *      7. InjectCSS instead of browser page reload.
 *      8. Generates .pot file for i18n and l10n.
 *
 */

/**
 * Load Gulp Configuration.
 */
const config = require("./gulp.config.js");

/**
 * Load Plugins.
 *
 * Load gulp plugins and passing them semantic names.
 */
const gulp = require("gulp");

// CSS related plugins.
const sass = require("gulp-sass")(require("sass"));
const cssnano = require("cssnano");
const cssnext = require("postcss-cssnext");
const postcss = require("gulp-postcss");
const rtlcss = require("gulp-rtlcss");

// JS related plugins.
const webpack = require("webpack-stream");
const concat = require("gulp-concat");
const uglify = require("gulp-uglify");
const babel = require("gulp-babel");

// Utility related plugins.
const rename = require("gulp-rename");
const lineec = require("gulp-line-ending-corrector");
const filter = require("gulp-filter");
const sourcemaps = require("gulp-sourcemaps");
const cache = require("gulp-cache");
const plumber = require("gulp-plumber");
const beep = require("beepbeep");
const gulpif = require("gulp-if");
const argv = require("yargs").argv;

// Variables Used within Build Process
const isProduction = argv.production !== undefined;
const postCssProd = [cssnext(), cssnano()];
const postCssDev = [cssnext()];

/**
 * Custom Error Handler.
 *
 * @param {*} r
 */
const errorHandler = (r) => {
  console.log("\n\n===> ERROR: " + r.message + "\n");
  beep();
};

/**
 * Task: `styles`.
 *
 * This task does the following:
 *    1. Gets the source scss file
 *    2. Compiles Sass to CSS
 *    3. Writes Sourcemaps for it
 *    4. Auto-prefixes it and generates style.css
 *    5. Renames the CSS file with suffix .min.css
 *    6. Minifies the CSS file and generates style.min.css
 */
gulp.task("styles", () => {
  return gulp
    .src(config.styleSRC, { allowEmpty: true })
    .pipe(plumber())
    .pipe(sourcemaps.init({}))
    .pipe(
      sass({
        errLogToConsole: config.errLogToConsole,
        outputStyle: config.outputStyle,
        precision: config.precision,
      }).on("error", sass.logError)
    )
    .pipe(gulpif(isProduction, postcss(postCssProd), postcss(postCssDev)))
    .pipe(sourcemaps.write("./", { includeContent: false }))
    .pipe(sourcemaps.init({ loadMaps: true }))
    // .pipe(sourcemaps.write("./", {}))
    .pipe(lineec()) // Consistent Line Endings for non UNIX systems.
    .pipe(gulp.dest(config.styleDestination))
    .pipe(filter("**/*.css")) // Filtering stream to only css files.
    .on("end", () => {
      console.log("\n\n===> Styles Compiled\n");
    });
});

/**
 * Task: `stylesRTL`.
 *
 * Compiles Sass, Autoprefixes it, Generates RTL stylesheet, and Minifies CSS.
 *
 * This task does the following:
 *    1. Gets the source scss file
 *    2. Compiles Sass to CSS
 *    4. Auto-prefixes it and generates style.css
 *    5. Renames the CSS file with suffix -rtl and generates style-rtl.css
 *    6. Writes Sourcemaps for style-rtl.css
 *    7. Renames the CSS files with suffix .min.css
 *    8. Minifies the CSS file and generates style-rtl.min.css
 *    9. Injects CSS or reloads the browser via browserSync
 */
gulp.task("stylesRTL", () => {
  return gulp
    .src(config.styleSRC, { allowEmpty: true })
    .pipe(plumber(errorHandler))
    .pipe(sourcemaps.init({}))
    .pipe(
      sass({
        errLogToConsole: config.errLogToConsole,
        outputStyle: config.outputStyle,
        precision: config.precision,
      })
    )
    .on("error", sass.logError)
    .pipe(gulpif(isProduction, postcss(postCssProd), postcss(postCssDev)))
    .pipe(sourcemaps.write("./", { includeContent: false }))
    .pipe(sourcemaps.init({ loadMaps: true }))
    .pipe(lineec()) // Consistent Line Endings for non UNIX systems.
    .pipe(rename({ suffix: "-rtl" })) // Append "-rtl" to the filename.
    .pipe(rtlcss()) // Convert to RTL.
    .pipe(gulp.dest(config.styleDestination))
    .pipe(filter("**/*.css")) // Filtering stream to only css files.
    .on("end", () => {
      console.log("\n\n===> Styles RTL Compiled\n");
    });
});

/**
 * Task: `customJS`.
 *
 * Concatenate and uglify custom JS scripts.
 *
 * This task does the following:
 *     1. Gets the source folder for JS custom files
 *     2. Concatenates all the files and generates custom.js
 *     3. Renames the JS file with suffix .min.js
 *     4. Uglifies/Minifies the JS file and generates custom.min.js
 */
gulp.task("customJS", () => {
  return gulp
    .src(config.jsCustomSRC, { allowEmpty: true }) // Only run on changed files.
		.pipe( plumber( errorHandler ) )
		.pipe( uglify() )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsCustomDestination ) )
    .on("end", () => {
      console.log("\n\n===> Javascript Compiled\n");
    });
});

/**
 * Task: `clear-images-cache`.
 *
 * Deletes the images cache. By running the next "images" task,
 * each image will be regenerated.
 */
gulp.task("clearCache", function (done) {
  return cache.clearAll(done);
});

/**
 * Watch Tasks.
 *
 * Watches for file changes and runs specific tasks.
 */
gulp.task(
  "default",
  gulp.parallel("styles", "customJS", () => {
    gulp.watch(config.watchStyles, gulp.parallel("styles")); // Reload on SCSS file changes.
    gulp.watch(config.watchJsCustom, gulp.series("customJS")); // Reload on customJS file changes.
  })
);

/**
 * Build for Production.
 *
 * Watches for file changes and runs specific tasks.
 */
gulp.task("production", gulp.parallel("styles", "customJS"));
