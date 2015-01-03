<?php
/*
Plugin Name: Styleguide
Plugin URI: http://wordpress.org/plugins/styleguide/
Description: Easily customise styles and fonts for the themes you use on your website.
Version: 1.0.0
Author: BinaryMoon
Author URI: http://www.binarymoon.co.uk
License: GPL2
*/

/**
 * Wishlist
 *
 * style templates
 * auto dequeue existing fonts (probably needs some properties in the add_theme_support)
 * allow fonts that aren't in the font list (to support themes with default fonts)
 * add intelligent defaults for properties
 * check if the color control already exists and if not create it
 * behave better when there's already defined colours (eg with Twenty Fifteen)
 */

class StyleGuide {

	private $colors = array();

	private $fonts = array();


	/**
	 *
	 */
	public function __construct() {

		// prevent duplication
		global $styleguide;

		if ( isset( $styleguide ) ) {
			return $styleguide;
		}

		// setup hooks
		add_action( 'after_setup_theme', array( &$this, 'check_compat' ), 99 );
		add_action( 'wp_head', array( &$this, 'process_styles' ), 99 );
		add_action( 'customize_register', array( &$this, 'setup_customizer' ) );
		add_action( 'customize_register', array( &$this, 'customize_register' ), 99 );
		add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_fonts' ) );

	}


	/**
	 * include theme compatability file if it exists
	 *
	 * @param type $theme_name
	 */
	function check_compat() {

		$current_theme = wp_get_theme();

		$theme_name = $current_theme->get( 'Name' );
		$theme_name = strtolower( $theme_name );
		$theme_name = str_replace( ' ', '-', $theme_name );

		$file = plugin_dir_path( __FILE__ ) . 'theme-styles/' . $theme_name . '.php';

		if ( file_exists( $file ) ) {
			include( $file );
		}

	}


	/**
	 * Enqueue the Google fonts
	 *
	 * @return type
	 */
	function enqueue_fonts() {

		$settings = get_theme_support( 'styleguide' );
		$settings = $settings[0];

		// make sure there's fonts to change
		if ( empty( $settings['fonts'] ) ) {
			return;
		}

		if ( $settings['fonts'] ) {

			$fonts = $this->process_fonts();

			// enqueue the fonts
			if ( $fonts ) {
				$query_args = array(
					'family' => urlencode( implode( '|', $fonts ) ),
					'subset' => urlencode( 'latin,latin-ext' ),
				);

				$fonts_url = add_query_arg( $query_args, '//fonts.googleapis.com/css' );

				wp_enqueue_style( 'styleguide-fonts', $fonts_url, array(), null );
			}

		}

	}


	/**
	 * output the css styles for the current theme
	 */
	function process_styles() {

		$settings = get_theme_support( 'styleguide' );

		if ( $settings ) {
			$settings = $settings[0];
		}

		if ( ! empty( $settings['colors'] ) ) {

			include_once( plugin_dir_path( __FILE__ ) . 'includes/csscolor.php' );

			// if a background color is set
			if ( current_theme_supports( 'custom-background' ) ) {
				$this->process_colors( 'theme-background', get_background_color() );
			}

			// other custom colors
			foreach( $settings['colors'] as $color_key => $color ) {
				$key = 'styleguide_color_' . $color_key;
				$this->process_colors( $color_key, get_theme_mod( $key, $color[ 'default' ] ) );
			}

			// if there's any color combos then do them too
			if ( ! empty( $settings['color-combos'] ) ) {
				foreach( $settings['color-combos'] as $combo_key => $combo ) {
					$key = 'styleguide_color_' . $combo['background'];
					$color1 = get_theme_mod( $key, $settings['colors'][ $combo['background'] ]['default'] );

					$key = 'styleguide_color_' . $combo['foreground'];
					$color2 = get_theme_mod( $key, $settings['colors'][ $combo['foreground'] ]['default'] );

					$this->process_colors( $combo_key, $color1, $color2 );
				}
			}

		}

		if ( ! empty( $settings['css'] ) ) {
			$this->output_css( $settings['css'] );
		}


	}


	/**
	 * change transport type for default customizer types
	 * means users can make use of colours for more things
	 *
	 * @param type $wp_customize
	 */
	function customize_register( $wp_customize ) {

		$settings = get_theme_support( 'styleguide', 'colors' );

		// make sure there's colors to change
		if ( empty( $settings ) ) {
			return;
		}

		// make custom background refresh the page rather than refresh with javascript
		if ( get_theme_support( 'custom-background', 'wp-head-callback' ) === '_custom_background_cb' ) {
			$wp_customize->get_setting( 'background_color' )->transport = 'refresh';
		}

		// make custom header refresh the page rather than refresh with javascript
		if ( get_theme_support( 'custom-header', 'wp-head-callback' ) === '_custom_background_cb' ) {
			// $wp_customize->get_setting( 'header_textcolor' )->transport = 'refresh';
		}

		// change section title
		$wp_customize->get_section( 'colors' )->title = __( 'Colors & Fonts', 'styleguide' );

	}


	/**
	 * print css to the head
	 *
	 * @param type $css
	 */
	function output_css( $css ) {

		// replace colours in the css template
		foreach( $this->colors as $key => $color ) {
			$css = str_replace( '{{color-' . $key . '}}', styleguide_sanitize_hex_color( $color ), $css );
		}

		// replace fonts in the css template
		foreach( $this->fonts as $key => $font ) {
			$css = str_replace( '{{font-' . $key . '}}', $font, $css );
		}

		$css = trim( $css );

		// output css
		echo '<!-- Styleguide styles -->' . "\r\n";
		echo '<style>' . $css . '</style>';

	}


	/**
	 * process the colours and save them for later use
	 *
	 * @param type $colors
	 * @param type $prefix
	 */
	function process_colors( $prefix, $color1, $color2 = null ) {

		if ( null !== $color2 ) {
			$colors = new CSS_Color( styleguide_sanitize_hex_color( $color1 ), styleguide_sanitize_hex_color( $color2 ) );
		} else {
			$colors = new CSS_Color( styleguide_sanitize_hex_color( $color1 ) );
		}

		$this->add_colors( $prefix . '-fg', $colors->fg );
		$this->add_colors( $prefix . '-bg', $colors->bg );

	}


	/**
	 *
	 * @return string
	 */
	function process_fonts() {

		$fonts = array();
		$available_fonts = styleguide_fonts();
		$settings = get_theme_support( 'styleguide' );

		if ( empty( $settings[0]['fonts'] ) ) {
			return $fonts;
		}

		// load chosen fonts
		foreach( $settings[0]['fonts'] as $font_key => $font ) {
			// make sure it's a google font and not a system font
			// by default all fonts are google fonts
			if ( ! isset( $font['google'] ) || true === $font['google'] ) {
				$key = 'styleguide_font_' . $font_key;
				$_font = get_theme_mod( $key, $font[ 'default' ] );

				// store font for use later
				if ( isset( $available_fonts[ $font[ 'default' ] ] ) ) {
					$this->fonts[ $font_key ] = $available_fonts[ $font[ 'default' ] ][ 'family' ];
				}

				if ( isset( $available_fonts[ $_font ] ) ) {
					$fonts[ $_font ] = $_font . ':400,700';
					$this->fonts[ $font_key ] = $available_fonts[ $_font ][ 'family' ];
				}
			}
		}

		return $fonts;

	}


	/**
	 * add colors to the global array so that they can be easily accessed
	 *
	 * @param type $colors
	 * @param type $prefix
	 */
	function add_colors( $prefix, $colors ) {

		foreach( $colors as $key => $color ) {
			if ( $key == '0' ) {
				$key = '-0';
			}
			$this->colors[ ($prefix . $key) ] = '#' . $color;
		}

	}


	/**
	 * setup the customizer control panel
	 */
	function setup_customizer( $wp_customize ) {

		$settings = get_theme_support( 'styleguide' );

		if ( $settings ) {

			$settings = $settings[0];
			$priority = 1;

			// add font controls
			if ( ! empty( $settings['fonts'] ) ) {

				// loop through fonts and output settings and controls
				foreach( $settings[ 'fonts' ] as $font_key => $font ) {

					$key = 'styleguide_font_' . $font_key;

					$wp_customize->add_setting( $key, array(
						'default' => '',
						'capability' => 'edit_theme_options',
						'sanitize_callback' => 'styleguide_sanitize_select',
					) );

					$wp_customize->add_control(
						$key,
						array(
							'label' => $font[ 'label' ],
							'section' => 'colors',
							'settings' => $key,
							'type' => 'select',
							'choices' => $this->get_fonts(),
							'priority' => $priority,
						)
					);

					$priority ++;

				}

			}

			// add color controls
			if ( ! empty( $settings[ 'colors' ] ) ) {

				// does the color control already exist (through background and header colour customization?
				// if not then create the control - else reuse the existing one



				// loop through colours and output controls
				foreach( $settings['colors'] as $color_key => $color ) {

					$key = 'styleguide_color_' . $color_key;

					$wp_customize->add_setting( $key, array(
						'default' => $color[ 'default' ],
						'capability' => 'edit_theme_options',
						'sanitize_callback' => 'styleguide_sanitize_hex_color',
					) );

					$wp_customize->add_control(
						new WP_Customize_Color_Control(
							$wp_customize,
							$key,
							array(
								'label' => $color[ 'label' ],
								'section' => 'colors',
								'settings' => $key,
								'priority' => $priority,
							)
						)
					);

					$priority ++;

				}


			}

		}

	}


	/**
	 * return the available fonts
	 */
	function get_fonts() {

		$_fonts = styleguide_fonts();

		$fonts = array(
			'' => __( 'Default', 'styleguide' ),
		);

		foreach( $_fonts as $key => $font ) {
			$fonts[ $key ] = $font[ 'name' ];
		}

		return $fonts;

	}

}

$styleguide = new StyleGuide();


/**
 * sanitize a hexadecimal colour
 *
 * @param type $color
 * @return string
 */
function styleguide_sanitize_hex_color( $color ) {

	if ( '' === $color ) {
		return '';
	}

	$color = str_replace( '#', '', $color );

	// 3 or 6 hex digits, or the empty string.
	if ( preg_match('|^([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
		return '#' . $color;
	}

	return null;

}


/**
 * make sure the value returned is in the fonts array
 *
 * @param type $id
 * @return type
 */
function styleguide_sanitize_select( $id ) {

	$_fonts = styleguide_fonts();

	if ( isset( $_fonts[ $id ] ) ) {
		return $id;
	}

	return null;

}


/**
 * get the available fonts
 *
 * @return type
 */
function styleguide_fonts() {

	$fonts = array(
		'Droid+Sans' => array(
			'name' => 'Droid Sans',
			'family' => '"Droid Sans", sans-serif',
		),
		'Droid+Serif' => array(
			'name' => 'Droid Serif',
			'family' => '"Droid Serif", serif',
		),
		'Georgia' => array(
			'name' => 'Georgia',
			'family' => 'Georgia, "Bitstream Charter", serif',
			'google' => false,
		),
		'Helvetica' => array(
			'name' => 'Helvetica',
			'family' => '"Helvetica Neue", Arial, Helvetica, "Nimbus Sans L", sans-serif',
			'google' => false,
		),
		'Lato' => array(
			'name' => 'Lato',
			'family' => 'Lato, sans-serif',
		),
		'Merriweather' => array(
			'name' => 'Merriweather',
			'family' => 'Merriweather, serif',
		),
		'Merriweather+Sans' => array(
			'name' => 'Merriweather Sans',
			'family' => '"Merriweather Sans", sans-serif',
		),
		'Noto+Sans' => array(
			'name' => 'Noto Sans',
			'family' => '"Noto Sans", sans-serif',
		),
		'Noto+Serif' => array(
			'name' => 'Noto Serif',
			'family' => '"Noto Serif", serif',
		),
		'Open+Sans' => array(
			'name' => 'Open Sans',
			'family' => '"Open Sans", sans-serif',
		),
		'Oswald' => array(
			'name' => 'Oswald',
			'family' => 'Oswald, sans-serif',
		),
		'Pt+Sans' => array(
			'name' => 'PT Sans',
			'family' => '"PT Sans", sans-serif',
		),
		'Raleway' => array(
			'name' => 'Raleway',
			'family' => 'Raleway, sans-serif',
		),
		'Roboto+Slab' => array(
			'name' => 'Roboto Slab',
			'family' => '"Roboto Slab", serif',
		),
		'Roboto' => array(
			'name' => 'Roboto',
			'family' => 'Roboto, sans-serif',
		),
		'Source+Sans+Pro' => array(
			'name' => 'Source Sans Pro',
			'family' => '"Source Sans Pro", sans-serif',
		),
		'Ubuntu' => array(
			'name' => 'Ubuntu',
			'family' => 'Ubuntu, sans-serif',
		),
	);

	return apply_filters( 'styleguide_get_fonts', $fonts );

}