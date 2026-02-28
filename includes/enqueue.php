<?php
defined('ABSPATH') || exit;

function quantyss_enqueue_assets() {
    // Swiper
    wp_register_style(
        'swiper-css',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        [],
        '11'
    );
    wp_register_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        '11',
        true
    );

    // CSS du slider Quantyss
    wp_register_style(
        'quantyss-slider',
        QUANTYSS_URL . 'assets/css/slider.css',
        ['swiper-css'],
        QUANTYSS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'quantyss_enqueue_assets');