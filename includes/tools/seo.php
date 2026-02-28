<?php
defined('ABSPATH') || exit;

// N'active que si Yoast/RankMath absents
function quantyss_seo_active() {
    return !defined('WPSEO_VERSION') && !defined('RANK_MATH_VERSION');
}

// -------------------------------------------------------
// BALISES META AUTOMATIQUES
// -------------------------------------------------------
function quantyss_inject_meta() {
    if (!quantyss_seo_active()) return;
    if (!is_singular()) return;

    global $post;
    $title       = get_the_title($post);
    $description = wp_trim_words(
        wp_strip_all_tags($post->post_content),
        25, '…'
    );
    $image       = get_the_post_thumbnail_url($post, 'large');
    $url         = get_permalink($post);
    ?>
    <!-- Quantyss SEO -->
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:title"       content="<?php echo esc_attr($title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url"         content="<?php echo esc_url($url); ?>">
    <meta property="og:type"        content="article">
    <?php if ($image) : ?>
        <meta property="og:image"   content="<?php echo esc_url($image); ?>">
        <meta name="twitter:card"   content="summary_large_image">
        <meta name="twitter:image"  content="<?php echo esc_url($image); ?>">
    <?php endif; ?>
    <meta name="twitter:title"      content="<?php echo esc_attr($title); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
    <!-- /Quantyss SEO -->
    <?php
}
add_action('wp_head', 'quantyss_inject_meta');

// -------------------------------------------------------
// SCHEMA JSON-LD AUTOMATIQUE
// -------------------------------------------------------
function quantyss_inject_schema() {
    if (!quantyss_seo_active()) return;

    if (is_singular('post')) {
        global $post;
        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => get_the_title($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified'  => get_the_modified_date('c', $post),
            'author'        => [
                '@type' => 'Person',
                'name'  => get_the_author_meta('display_name', $post->post_author),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url(),
            ],
            'url'           => get_permalink($post),
            'description'   => wp_trim_words(wp_strip_all_tags($post->post_content), 25, '…'),
        ];

        $thumbnail = get_the_post_thumbnail_url($post, 'large');
        if ($thumbnail) $schema['image'] = $thumbnail;

        echo '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>' . "\n";
    }

    if (is_front_page()) {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            'name'        => get_bloginfo('name'),
            'url'         => home_url(),
            'description' => get_bloginfo('description'),
        ];
        echo '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>' . "\n";
    }
}
add_action('wp_head', 'quantyss_inject_schema');

// -------------------------------------------------------
// CANONICAL AUTOMATIQUE
// -------------------------------------------------------
function quantyss_inject_canonical() {
    if (!quantyss_seo_active()) return;
    if (!is_singular()) return;
    echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">' . "\n";
}
add_action('wp_head', 'quantyss_inject_canonical');

// -------------------------------------------------------
// SITEMAP DYNAMIQUE
// -------------------------------------------------------
function quantyss_register_sitemap_rewrite() {
    add_rewrite_rule('^quantyss-sitemap\.xml$', 'index.php?quantyss_sitemap=1', 'top');
}
add_action('init', 'quantyss_register_sitemap_rewrite');

function quantyss_sitemap_query_var($vars) {
    $vars[] = 'quantyss_sitemap';
    return $vars;
}
add_filter('query_vars', 'quantyss_sitemap_query_var');

function quantyss_render_sitemap() {
    if (!get_query_var('quantyss_sitemap')) return;

    $posts = get_posts([
        'post_type'      => ['post', 'page'],
        'post_status'    => 'publish',
        'posts_per_page' => 500,
    ]);

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    echo '<url><loc>' . esc_url(home_url('/')) . '</loc>'
       . '<changefreq>weekly</changefreq><priority>1.0</priority></url>';

    foreach ($posts as $post) {
        echo '<url>'
           . '<loc>' . esc_url(get_permalink($post)) . '</loc>'
           . '<lastmod>' . get_the_modified_date('c', $post) . '</lastmod>'
           . '<changefreq>monthly</changefreq>'
           . '<priority>' . ($post->post_type === 'page' ? '0.8' : '0.6') . '</priority>'
           . '</url>';
    }

    echo '</urlset>';
    exit;
}
add_action('template_redirect', 'quantyss_render_sitemap');