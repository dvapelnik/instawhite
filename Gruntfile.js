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
          'css/framework.min.css': 'css-framework/css/framework.min.css'
        }
      },
      js: {
        files: {
          'js/html5shiv.min.js': 'html5shiv/dist/html5shiv.min.js'
        }
      }
    }
  });

  grunt.loadNpmTasks('grunt-bowercopy');
  grunt.registerTask('default', ['bowercopy']);
};