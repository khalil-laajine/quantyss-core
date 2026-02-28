<?php
defined('ABSPATH') || exit;

function quantyss_register_elementor_widgets($widgets_manager) {
    require_once QUANTYSS_PATH . 'includes/elementor/widgets/slider-widget.php';
    $widgets_manager->register(new \Quantyss\Widgets\Slider_Widget());
}
add_action('elementor/widgets/register', 'quantyss_register_elementor_widgets');

function quantyss_register_elementor_category($elements_manager) {
    $elements_manager->add_category('quantyss', [
        'title' => 'Quantyss',
        'icon'  => 'fa fa-plug',
    ]);
}
add_action('elementor/elements/categories_registered', 'quantyss_register_elementor_category');