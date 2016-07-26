<?php
/**
 * Plugin Name:  WP-SCSS
 * Plugin URI:   https://github.com/TtrampolineDigital/wp-scss/
 * Description:  Allows you to enqueue <code>.scss</code> files and have them automatically compiled whenever a change is detected.
 * Author:       Trampoline Digital
 * Contributors: Robert O'Rourke, Franz Josef Kaiser, Tom Willmot, Rarst
 * Version:      2.1
 * Author URI:   http://trampolinedigital.com
 * License:      MIT
 */

// Busted! No direct file access
! defined( 'ABSPATH' ) AND exit;

// load the autoloader if it's present


if ( file_exists( get_template_directory() . '/vendor/autoload.php' ) ) {
	require get_template_directory() . '/vendor/autoload.php';
} else {
	if ( file_exists( get_template_directory() . '/vendor/leafo/scssphp/scssc.inc.php' ) ) {
		// load SCSS parser
		require_once( get_template_directory() . '/vendor/leafo/scssphp/scssc.inc.php' );
	}
}


if ( ! class_exists( 'WP_SCSS' ) ) {
	// add on init to support theme customiser in v3.4
	add_action( 'init', array( 'WP_SCSS', 'instance' ) );

	/**
	 * Enables the use of SCSS in WordPress
	 *
	 * See README.md for usage information
	 *
	 * @author  Robert "sancho the fat" O'Rourke and Dan Green
	 * @link    http://sanchothefat.com/
	 * @package WP SCSS
	 * @license MIT
	 * @version 0.1
	 */
	class WP_SCSS {
		/**
		 * @static
		 * @var    \WP_SCSS Reusable object instance.
		 */
		protected static $instance = null;


		/**
		 * Creates a new instance. Called on 'after_setup_theme'.
		 * May be used to access class methods from outside.
		 *
		 * @see    __construct()
		 * @static
		 * @return \WP_SCSS
		 */
		public static function instance() {
			null === self:: $instance AND self:: $instance = new self;
			return self:: $instance;
		}


		/**
		 * @var array Array store of callable functions used to extend the parser
		 */
		public $registered_functions = array();

        /**
         * @var string The directory where the original scss is
         */
        protected $scss_directory;

        /**
         * @var string The directory where we will save the css and cache
         */
        protected $css_directory;

        /**
         * @var string The full path of the scss file we are dealing with
         */
        protected $src_path;

        /**
         * @var string The handle we are dealing with
         */
        protected $handle;

		/**
		 * @var array Array store of function names to be removed from the compiler class
		 */
		public $unregistered_functions = array();

		/**
		 * @var array Variables to be passed into the compiler
		 */
		public $vars = array();

		/**
		 * @var string Compression class to use
		 */
		public $compression = 'compressed';

		/**
		 * @var bool Whether to preserve comments when compiling
		 */
		public $preserve_comments = false;

		/**
		 * @var array Default import directory paths for SCSS to scan
		 */
		public $import_dirs = array();


        /*************************
         ** Getters and Setters **
         *************************/


        /**
         * Get the css directory
         * @return string
         */
        public function get_css_directory(){
            return $this->css_directory;
        }

        /**
         * Get the scss directory
         * @return string
         */
        public function get_scss_directory(){
            return $this->scss_directory;
        }

        /**
         * Set the scss directory
         * @param string $directory
         */
        public function set_scss_directory($directory){
            $this->scss_directory = $directory;
        }

        /**
         * Get the handle
         * @return string
         */
        public function get_handle(){
            return $this->handle;
        }

        /**
         * Set the handle
         * @param string $handle
         */
        public function set_handle($handle){
            $this->handle = $handle;
        }

        /**
         * Get the src path
         * @return string
         */
        public function get_src_path(){
            return $this->src_path;
        }

        /**
         * Set the src path
         * @param string $src_path
         */
        public function set_src_path($src_path){
            $this->src_path = $src_path;
        }

        /**
         * Set the vars from customizer, etc
         * This handles turning arrays of values into maps
         * @param array $vars
         */
        public function add_vars($vars){
            foreach ($vars as $key => $value){
                if (is_array($vars[$key])){
                    // This like converts the php array into an SCSS map
                    $vars[$key] = str_replace(
                        array('{','}','"'),
                        array('(',')',''),
                        json_encode($vars[$key])
                    );
                }
            }
            $this->vars = array_merge($this->vars, $vars);
        }

        /**
         * Get the vars
         * @return array
         */
        public function get_vars(){
            return $this->vars;
        }

        /**
         * Adds an interface to register SCSS functions. See the documentation
         * for details: http://leafo.github.io/scssphp/docs/#custom_functions
         *
         * @param  string $name The name for function used in the less file eg. 'makebluer'
         * @param  string $callable (callback) Callable method or function that returns a lessc variable
         * @return void
         */
        public function register( $name, $callable ) {
            $this->registered_functions[ $name ] = $callable;
        }

        /**
         * Unregisters a function
         *
         * @param  string $name The function name to unregister
         * @return void
         */
        public function unregister( $name ) {
            $this->unregistered_functions[ $name ] = $name;
        }


        /**
         * Add less var prior to compiling
         *
         * @param  string $name The variable name
         * @param  string $value The value for the variable as a string
         * @return void
         */
        public function add_var( $name, $value ) {
            if ( is_string( $name ) ) {
                $this->vars[ $name ] = $value;
            }
        }

        /**
         * Removes a less var
         *
         * @param  string $name Name of the variable to remove
         * @return void
         */
        public function remove_var( $name ) {
            if ( isset( $this->vars[ $name ] ) ) {
                unset( $this->vars[ $name ] );
            }
        }

		/**
		 * Constructor
		 */
		public function __construct() {

			// every CSS file URL gets passed through this filter
			add_filter( 'style_loader_src', array( $this, 'parse_stylesheet' ), 100000, 2 );

			// editor stylesheet URLs are concatenated and run through this filter
			add_filter( 'mce_css', array( $this, 'parse_editor_stylesheets' ), 100000 );

			// exclude from official repo update check
			add_filter( 'http_request_args', array( $this, 'http_request_args' ), 5, 2 );

            $this->css_directory = wp_upload_dir()['basedir']."/wp-scss-cache";
            if (!is_dir($this->css_directory)) {
                mkdir($this->css_directory, 0777, true);
            }
		}

		
		/**
		 * SCSSify the stylesheet and return the href of the compiled file
		 *
		 * @param  string $src Source URL of the file to be parsed
		 * @param  string $handle An identifier for the file used to create the file name in the cache
		 * @return string         URL of the compiled stylesheet
		 */
		public function parse_stylesheet( $src, $handle ) {

			// we only want to handle .scss files
			if ( ! preg_match( '/\.scss(\.php)?$/', preg_replace( '/\?.*$/', '', $src ) ) ) {
				return $src;
			}

			// get file path from $src
			if ( ! strstr( $src, '?' ) ) {
				$src .= '?';
			} // prevent non-existent index warning when using list() & explode()


			// vars to pass into the compiler - default @themeurl var for image urls etc...
			$this->add_vars(array(
			    'theme-url'=> '~"' . get_template_directory_uri() . '"'
            ));
            // Lets get the paths we need
            $scss_directory = str_replace(get_template_directory_uri()."/", "", $src);
            $scss_directory = substr($scss_directory, 0,strrpos($scss_directory, '/'));
            $scss_directory = get_template_directory()."/$scss_directory";

            $scss_filename = substr(basename($src), 0,strrpos(basename($src), '?'));
            $css_filename = str_replace("scss", "css", $scss_filename);
            $css_directory_uri = wp_upload_dir()['baseurl']."/wp-scss-cache";

            $this->set_scss_directory($scss_directory);
            $this->set_handle($handle);
            $this->set_src_path("$scss_directory/$scss_filename");

            $this->add_vars(
                apply_filters( 'scss_vars', $this->get_vars(), $handle )
            );

            $this->add_vars(
                array('color-map'=> array(
                        "blue"=>"green",
                        "yellow"=> "purple"
                    ))
            );

            // Don't recompile if the neither the vars nor the source have changed
            if ( !$this->scss_is_changed() && !WP_DEBUG){
                return "$css_directory_uri/$css_filename";
            }

			// Do recompile if either the vars or soure have changed
			try {
				$scss = new \Leafo\ScssPhp\Compiler();
				$scss->setVariables( $this->vars );
                $scss->addImportPath($scss_directory);
                $compiled_css = $scss->compile(file_get_contents("$scss_directory/$scss_filename"));
                $this->save_parsed_css($this->get_css_directory()."/$css_filename", $compiled_css );

			} catch ( Exception $ex ) {
				wp_die( $ex->getMessage() );
			}

            return "$css_directory_uri/$css_filename";
		}

        /**
         * Hash a directory (for the sake of checking for changes)
         *
         * @param  string $directory The absolute path to the directory
         * @return string Hash of the directory
         */
        function hash_directory($directory){
            if (! is_dir($directory)) {
                return false;
            }
            $files = array();
            $dir = dir($directory);
            while (false !== ($file = $dir->read())) {
                if ($file != '.' and $file != '..') {
                    if (is_dir($directory . '/' . $file)) {
                        $files[] = $this->hash_directory($directory . '/' . $file);
                    }
                    else
                    {
                        $files[] = md5_file($directory . '/' . $file);
                    }
                }
            }
            $dir->close();
            return md5(implode('', $files));
        }

        /**
         * Check for a change in the scss
         * @return boolean
         */
        public function scss_is_changed(){

            $hash_file_location = $this->get_css_directory()."/".$this->get_handle()."-hash.txt";
            if (!file_exists($hash_file_location)){
                file_put_contents ($hash_file_location, "No cache yet" );
            }

            $hash_file = fopen($hash_file_location, "r");
            $old_hash = fread($hash_file, filesize($hash_file_location));
            fclose($hash_file);

            $scss_file = fopen($this->src_path, "r");
            $scss_file_contents = fread($scss_file, filesize($this->src_path));
            fclose($scss_file);

            $new_hash = $this->hash_directory($this->get_scss_directory());
            $new_hash .= implode("",$this->get_vars()) . $scss_file_contents;
            $new_hash = md5($new_hash);
            if ($old_hash != $new_hash){
                file_put_contents ($hash_file_location, $new_hash );
                return true;
            }
            return false;
        }

        /**
         *
         * @param $css_path
         * @param $file_contents
         */
		public function save_parsed_css( $css_path, $file_contents ) {
			if ( ! apply_filters( 'scss_save_css', $css_path, $file_contents ) ) {
				return;
			}
			file_put_contents( $css_path, $file_contents );
		}

		/**
		 * Compile editor stylesheets registered via add_editor_style()
		 *
		 * @param  string $mce_css Comma separated list of CSS file URLs
		 * @return string $mce_css New comma separated list of CSS file URLs
		 */
		public function parse_editor_stylesheets( $mce_css ) {

			// extract CSS file URLs
			$style_sheets = explode( ",", $mce_css );

			if ( count( $style_sheets ) ) {
				$compiled_css = array();

				// loop through editor styles, any .scss files will be compiled and the compiled URL returned
				foreach ( $style_sheets as $style_sheet ) {
					$compiled_css[] = $this->parse_stylesheet( $style_sheet, $this->url_to_handle( $style_sheet ) );
				}

				$mce_css = implode( ",", $compiled_css );
			}

			// return new URLs
			return $mce_css;
		}

		/**
		 * Get a nice handle to use for the compiled CSS file name
		 *
		 * @param  string $url File URL to generate a handle from
		 * @return string $url Sanitized string to use for handle
		 */
		public function url_to_handle( $url ) {

			$url = parse_url( $url );
			$url = str_replace( '.scss', '', basename( $url['path'] ) );
			$url = str_replace( '/', '-', $url );

			return sanitize_key( $url );
		}

		/**
		 * Get (and create if unavailable) the compiled CSS cache directory
		 *
		 * @param  bool $path If true this method returns the cache's system path. Set to false to return the cache URL
		 * @return string $dir  The system path or URL of the cache folder
		 */
		public function get_cache_dir( $path = true ) {

			// get path and url info
			$upload_dir = wp_upload_dir();

			if ( $path ) {
				$dir = apply_filters( 'wp_scss_cache_path', path_join( $upload_dir['basedir'], 'wp-scss-cache' ) );
				// create folder if it doesn't exist yet
				if ( ! file_exists( $dir ) ) {
					wp_mkdir_p( $dir );
				}
			} else {
				$dir = apply_filters( 'wp_scss_cache_url', path_join( $upload_dir['baseurl'], 'wp-scss-cache' ) );
			}

			return rtrim( $dir, '/' );
		}

	} // END class

	if ( ! function_exists( 'register_scss_function' ) && ! function_exists( 'unregister_scss_function' ) ) {
		/**
		 * Register additional functions you can use in your less stylesheets. You have access
		 * to the full WordPress API here so there's lots you could do.
		 *
		 * @param  string $name The name of the function
		 * @param  string $callable (callback) A callable method or function recognisable by call_user_func
		 * @return void
		 */
		function register_scss_function( $name, $callable ) {
			$scss = WP_SCSS::instance();
			$scss->register( $name, $callable );
		}

		/**
		 * Remove any registered lessc functions
		 *
		 * @param  string $name The function name to remove
		 * @return void
		 */
		function unregister_scss_function( $name ) {
			$scss = WP_SCSS::instance();
			$scss->unregister( $name );
		}
	}

	if ( ! function_exists( 'add_scss_var' ) && ! function_exists( 'remove_scss_var' ) ) {
		/**
		 * A simple method of adding less vars via a function call
		 *
		 * @param  string $name The name of the function
		 * @param  string $value A string that will converted to the appropriate variable type
		 * @return void
		 */
		function add_scss_var( $name, $value ) {
			$scss = WP_SCSS::instance();
			$scss->add_var( $name, $value );
		}

		/**
		 * Remove less vars by array key
		 *
		 * @param  string $name The array key of the variable to remove
		 * @return void
		 */
		function remove_scss_var( $name ) {
			$scss = WP_SCSS::instance();
			$scss->remove_var( $name );
		}
	}

} // endif;
