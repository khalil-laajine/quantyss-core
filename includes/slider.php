<?php
defined('ABSPATH') || exit;

function quantyss_slider_shortcode($atts) {
    $atts = shortcode_atts([
        'posts'    => 6,
        'category' => '',
    ], $atts, 'slider_quantyss');

    $current_lang = function_exists('pll_current_language')
        ? pll_current_language()
        : 'fr';

    $btn_text = $current_lang === 'en' ? 'Read more' : 'Lire la suite';

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => (int) $atts['posts'],
        'post_status'    => 'publish',
        'lang'           => $current_lang,
    ];

    if (!empty($atts['category'])) {
        $args['category_name'] = sanitize_text_field($atts['category']);
    }

    $query = new WP_Query($args);
    if (!$query->have_posts()) return '';

    // On charge les assets seulement si le shortcode est utilisé
    wp_enqueue_style('quantyss-slider');
    wp_enqueue_script('swiper-js');

    $slider_id = 'q-slider-' . uniqid();

    ob_start(); ?>

    <div class="q-container">
        <div class="swiper q-slider" id="<?php echo esc_attr($slider_id); ?>">
            <div class="swiper-wrapper">
                <?php while ($query->have_posts()) : $query->the_post();
                    $thumbnail_id = get_post_thumbnail_id();
                    $img_alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true)
                               ?: get_the_title();
                ?>
                    <div class="swiper-slide">
                        <article class="q-card">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="q-img">
                                    <?php the_post_thumbnail('medium', ['alt' => esc_attr($img_alt)]); ?>
                                </div>
                            <?php endif; ?>

                            <div class="q-content">
                                <time class="q-date" datetime="<?php echo get_the_date('c'); ?>">
                                    <?php echo esc_html(get_the_date()); ?>
                                </time>
                                <h3><?php the_title(); ?></h3>
                                <p class="q-excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 18, '…'); ?>
                                </p>
                                <a href="<?php the_permalink(); ?>"
                                   class="q-btn"
                                   aria-label="<?php echo esc_attr($btn_text . ' : ' . get_the_title()); ?>">
                                    <?php echo esc_html($btn_text); ?>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        </article>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new Swiper('#<?php echo esc_js($slider_id); ?>', {
            slidesPerView: 1.2,
            spaceBetween: 20,
            grabCursor: true,
            loop: true,
            pagination: { el: '#<?php echo esc_js($slider_id); ?> .swiper-pagination', clickable: true },
            breakpoints: {
                768:  { slidesPerView: 2 },
                1024: { slidesPerView: 3 }
            }
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('slider_quantyss', 'quantyss_slider_shortcode');