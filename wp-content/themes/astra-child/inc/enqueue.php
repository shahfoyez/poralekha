<?php
/**
 * Enqueue child theme assets.
 */
function astra_child_enqueue_assets() {

    $theme_uri  = get_stylesheet_directory_uri();
    $theme_path = get_stylesheet_directory();

    wp_enqueue_style(
        'astra-child-style',
        get_stylesheet_uri(),
        array( 'astra-theme-css' ),
        filemtime( $theme_path . '/style.css' )
    );

    wp_enqueue_style(
        'astra-child-main',
        $theme_uri . '/assets/css/main.css',
        array( 'astra-child-style' ),
        filemtime( $theme_path . '/assets/css/main.css' )
    );
    wp_enqueue_style(
        'poralekha-custom-header',
        get_stylesheet_directory_uri() . '/assets/css/custom-header.css',
        array(),
        time()
    );
    wp_enqueue_style(
        'astra-child-main',
        $theme_uri . '/assets/css/courses.css',
        array( 'astra-child-style' ),
        filemtime( $theme_path . '/assets/css/course-single.css' )
    );

    wp_enqueue_style(
        'astra-child-course',
        $theme_uri . '/assets/css/course.css',
        array( 'astra-child-main' ),
        filemtime( $theme_path . '/assets/css/courses.css' )
    );
    wp_enqueue_script(
        'astra-child-main',
        get_stylesheet_directory_uri() . '/assets/js/main.js',
        array( 'jquery' ),
        filemtime(
            get_stylesheet_directory() . '/assets/js/main.js'
        ),
        true
    );
    wp_enqueue_script(
        'astra-child-main',
        get_stylesheet_directory_uri() . '/assets/js/courses.js',
        array( 'jquery' ),
        filemtime(
            get_stylesheet_directory() . '/assets/js/courses.js'
        ),
        true
    );
    wp_enqueue_script(
        'poralekha-custom-header',
        get_stylesheet_directory_uri() . '/assets/js/custom-header.js',
        array(),
        time(),
        true
    );
}

add_action( 'wp_enqueue_scripts', 'astra_child_enqueue_assets' );
