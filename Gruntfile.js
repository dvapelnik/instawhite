module.exports = function (grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        bowercopy: {
            options: {
                srcPrefix: 'bower_components',
                destPrefix: 'web/assets'
            },
            stylesheets: {
                files: {
                    'css/framework.min.css': 'css-framework/css/framework.min.css',
                    'colorpicker/jquery.colorpicker.css': 'colorpicker/jquery.colorpicker.css',
                    'css/jqueri-ui/themes': 'jquery-ui/themes'
                }
            },
            js: {
                files: {
                    'js/html5shiv.min.js': 'html5shiv/dist/html5shiv.min.js',
                    'js/jquery.colorpicker.js': 'colorpicker/jquery.colorpicker.js',
                    'js/jquery.min.js': 'jquery/dist/jquery.min.js',
                    'js/jquery-ui.min.js': 'bower_components/jquery-ui/jquery-ui.min.js'
                }
            },
            images: {
                files: {
                    'colorpicker/images': 'colorpicker/images'
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-bowercopy');
    grunt.registerTask('default', ['bowercopy']);
};