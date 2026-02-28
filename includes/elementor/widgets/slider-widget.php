<?php
namespace Quantyss\Widgets;

defined('ABSPATH') || exit;

class Slider_Widget extends \Elementor\Widget_Base {

    // Identifiant unique du widget
    public function get_name() {
        return 'quantyss_slider';
    }

    // Nom affiché dans le panel Elementor
    public function get_title() {
        return 'Quantyss — Slider Articles';
    }

    // Icône (bibliothèque Elementor Icons)
    public function get_icon() {
        return 'eicon-posts-carousel';
    }

    // Catégorie dans le panel
    public function get_categories() {
        return ['quantyss'];
    }

    // Mots clés pour la recherche
    public function get_keywords() {
        return ['slider', 'articles', 'posts', 'quantyss'];
    }

    // Chargement des assets Swiper
    public function get_style_depends() {
        return ['swiper-css', 'quantyss-slider'];
    }

    public function get_script_depends() {
        return ['swiper-js'];
    }

    // -------------------------------------------------------
    // PANNEAU DE CONTRÔLE — ce que la CEO voit dans Elementor
    // -------------------------------------------------------
    protected function register_controls() {

        // Section Contenu
        $this->start_controls_section('section_content', [
            'label' => 'Contenu',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

            $this->add_control('posts_per_page', [
                'label'   => 'Nombre d\'articles',
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 12,
                'default' => 6,
            ]);

            $this->add_control('category', [
                'label'       => 'Catégorie (slug)',
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'ex: intelligence-artificielle',
                'default'     => '',
            ]);

            $this->add_control('show_excerpt', [
                'label'        => 'Afficher le résumé',
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => 'Oui',
                'label_off'    => 'Non',
                'return_value' => 'yes',
                'default'      => 'yes',
            ]);

            $this->add_control('excerpt_words', [
                'label'     => 'Nombre de mots du résumé',
                'type'      => \Elementor\Controls_Manager::NUMBER,
                'min'       => 5,
                'max'       => 40,
                'default'   => 18,
                'condition' => ['show_excerpt' => 'yes'],
            ]);

        $this->end_controls_section();

        // Section Style
        $this->start_controls_section('section_style', [
            'label' => 'Style des cartes',
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

            $this->add_control('card_bg', [
                'label'     => 'Couleur de fond',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .q-card' => 'background-color: {{VALUE}};',
                ],
                'default'   => '#ffffff',
            ]);

            $this->add_control('title_color', [
                'label'     => 'Couleur du titre',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .q-card h3' => 'color: {{VALUE}};',
                ],
            ]);

            $this->add_group_control(
                \Elementor\Group_Control_Box_Shadow::get_type(),
                [
                    'name'     => 'card_shadow',
                    'label'    => 'Ombre',
                    'selector' => '{{WRAPPER}} .q-card',
                ]
            );

            $this->add_control('btn_color', [
                'label'     => 'Couleur du bouton',
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .q-btn' => 'color: {{VALUE}};',
                ],
            ]);

        $this->end_controls_section();
    }

    // -------------------------------------------------------
    // RENDU FRONT-END
    // -------------------------------------------------------
    protected function render() {
        $settings = $this->get_settings_for_display();

        $current_lang = function_exists('pll_current_language')
            ? pll_current_language()
            : 'fr';

        $btn_text = $current_lang === 'en' ? 'Read more' : 'Lire la suite';

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => (int) $settings['posts_per_page'],
            'post_status'    => 'publish',
            'lang'           => $current_lang,
        ];

        if (!empty($settings['category'])) {
            $args['category_name'] = sanitize_text_field($settings['category']);
        }

        $query = new \WP_Query($args);
        if (!$query->have_posts()) return;

        $slider_id = 'q-slider-' . $this->get_id();
        ?>

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

                                    <?php if ($settings['show_excerpt'] === 'yes') : ?>
                                        <p class="q-excerpt">
                                            <?php echo wp_trim_words(
                                                get_the_excerpt(),
                                                (int) $settings['excerpt_words'],
                                                '…'
                                            ); ?>
                                        </p>
                                    <?php endif; ?>

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
                pagination: {
                    el: '#<?php echo esc_js($slider_id); ?> .swiper-pagination',
                    clickable: true
                },
                breakpoints: {
                    768:  { slidesPerView: 2 },
                    1024: { slidesPerView: 3 }
                }
            });
        });
        </script>

        <?php
    }
}