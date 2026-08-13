<?php

if (!defined('ABSPATH')) {
    exit;
}

function ps_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus(['primary' => '주 메뉴']);
}
add_action('after_setup_theme', 'ps_setup');

function ps_assets(): void
{
    wp_enqueue_style('phone-seoul', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
    wp_enqueue_script(
        'phone-seoul',
        get_template_directory_uri() . '/assets/site.js',
        [],
        wp_get_theme()->get('Version'),
        true
    );
    wp_script_add_data('phone-seoul', 'strategy', 'defer');
    wp_localize_script('phone-seoul', 'PhoneSeoul', [
        'searchUrl' => esc_url_raw(rest_url('phone-catalog/v1/search')),
        'compareBase' => trailingslashit(home_url('/compare/')),
        'eventUrl' => esc_url_raw(rest_url('phone-catalog/v1/event')),
    ]);
}
add_action('wp_enqueue_scripts', 'ps_assets');

function ps_resource_hints(array $urls, string $relation_type): array
{
    return $urls;
}
add_filter('wp_resource_hints', 'ps_resource_hints', 10, 2);

function ps_allow_naver_yeti(string $output, bool $public): string
{
    $sitemap = 'Sitemap: ' . home_url('/sitemap_index.xml');
    if (preg_match('/^Sitemap:\s*\S+/mi', $output)) {
        $output = (string) preg_replace('/^Sitemap:\s*\S+/mi', $sitemap, $output);
    } else {
        $output = rtrim($output) . "\n\n" . $sitemap . "\n";
    }
    if (stripos($output, 'User-agent: Yeti') === false) {
        $output = rtrim($output) . "\n\nUser-agent: Yeti\nAllow: /\n";
    }
    return $output;
}
add_filter('robots_txt', 'ps_allow_naver_yeti', 9999, 2);

function ps_serve_naver_verification_file(): void
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path !== '/navere7f35168eb88592ccbdae3f4dbb225bc.html') {
        return;
    }
    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    echo 'naver-site-verification: navere7f35168eb88592ccbdae3f4dbb225bc.html';
    exit;
}
add_action('template_redirect', 'ps_serve_naver_verification_file', 0);

function ps_favicon(): void
{
    if (!has_site_icon()) {
        echo '<link rel="icon" href="data:image/svg+xml,' .
            rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="8" fill="#101828"/><path d="M14 18h36v8H36v25h-8V26H14z" fill="#fff"/><path d="M36 30h14v8H36z" fill="#2457f5"/></svg>') .
            '">' . "\n";
    }
}
add_action('wp_head', 'ps_favicon', 2);

function ps_catalog_search_scope(WP_Query $query): void
{
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        $keyword = trim((string) $query->get('s'));
        $type = sanitize_key((string) ($_GET['search_type'] ?? 'all'));
        if (!in_array($type, ['all', 'phone', 'laptop', 'cpu', 'gpu', 'ssd'], true)) {
            $type = 'all';
        }
        $ids = function_exists('pc_search_post_ids') ? pc_search_post_ids($keyword, $type, 5000) : [];
        $query->set('post_type', $type === 'all' ? ['phone', 'laptop', 'cpu', 'gpu', 'ssd'] : [$type]);
        $query->set('post__in', $ids ?: [0]);
        $query->set('orderby', 'post__in');
        $query->set('posts_per_page', 24);
        $query->set('ignore_sticky_posts', true);
        $query->set('pc_ranked_catalog_search', true);
    }
}
add_action('pre_get_posts', 'ps_catalog_search_scope', 30);

function ps_dense_hardware_archives(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive(['laptop', 'cpu', 'gpu', 'ssd'])) {
        return;
    }
    $query->set('posts_per_page', $query->is_post_type_archive('ssd') ? 24 : 80);
    $query->set('meta_key', '_catalog_release_date');
    $query->set('orderby', ['meta_value' => 'DESC', 'title' => 'ASC']);
    $query->set('order', 'DESC');
    $brand = sanitize_title((string) get_query_var('tech_brand'));
    if ($brand) {
        $query->set('tax_query', [[
            'taxonomy' => 'hardware_brand',
            'field' => 'slug',
            'terms' => $brand,
        ]]);
    }
}
add_action('pre_get_posts', 'ps_dense_hardware_archives', 35);

function ps_register_ssd_landing_routes(): void
{
    add_rewrite_rule(
        '^ssds/(1tb|2tb|4tb|nvme-gen4|nvme-gen5|sata|ps5-compatible|tlc|qlc|dram|hmb|high-endurance)/?$',
        'index.php?post_type=ssd&ssd_landing=$matches[1]',
        'top'
    );
}
add_action('init', 'ps_register_ssd_landing_routes', 5);

function ps_ssd_landing_labels(): array
{
    return [
        '1tb' => '1TB SSD', '2tb' => '2TB SSD', '4tb' => '4TB SSD',
        'nvme-gen4' => 'PCIe 4.0 NVMe SSD', 'nvme-gen5' => 'PCIe 5.0 NVMe SSD',
        'sata' => 'SATA SSD', 'ps5-compatible' => 'PS5 호환 SSD',
        'tlc' => 'TLC SSD', 'qlc' => 'QLC SSD', 'dram' => 'DRAM 탑재 SSD',
        'hmb' => 'HMB SSD', 'high-endurance' => '고내구성 SSD',
    ];
}

function ps_filter_ssd_landing(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('ssd')) return;
    $landing = sanitize_key((string) get_query_var('ssd_landing'));
    $needles = [
        '1tb' => '"field":"Capacity","value":"1 TB',
        '2tb' => '"field":"Capacity","value":"2 TB',
        '4tb' => '"field":"Capacity","value":"4 TB',
        'nvme-gen4' => '"field":"Interface","value":"PCIe 4',
        'nvme-gen5' => '"field":"Interface","value":"PCIe 5',
        'sata' => '"field":"Interface","value":"SATA',
        'ps5-compatible' => '"field":"PS5 Compatible","value":"Yes"',
        'tlc' => '"field":"Type","value":"TLC"',
        'qlc' => '"field":"Type","value":"QLC"',
        'dram' => '"field":"Controller Features","value":"DRAM',
        'hmb' => 'HMB',
        'high-endurance' => '',
    ];
    if (!isset($needles[$landing])) return;
    if ($landing === 'high-endurance') return;
    $meta_query = (array) $query->get('meta_query');
    $meta_query[] = ['key' => '_tech_specs', 'value' => $needles[$landing], 'compare' => 'LIKE'];
    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'ps_filter_ssd_landing', 40);

function ps_ssd_advanced_filter_where(string $where, WP_Query $query): string
{
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('ssd')) return $where;
    global $wpdb;
    $filters = [
        'ssd_capacity' => ['1tb' => '"field":"Capacity","value":"1 TB', '2tb' => '"field":"Capacity","value":"2 TB', '4tb' => '"field":"Capacity","value":"4 TB'],
        'ssd_interface' => ['pcie3' => '"field":"Interface","value":"PCIe 3', 'pcie4' => '"field":"Interface","value":"PCIe 4', 'pcie5' => '"field":"Interface","value":"PCIe 5', 'sata' => '"field":"Interface","value":"SATA'],
        'ssd_nand' => ['tlc' => '"field":"Type","value":"TLC"', 'qlc' => '"field":"Type","value":"QLC"'],
        'ssd_cache' => ['dram' => '"field":"Controller Features","value":"DRAM', 'hmb' => 'HMB', 'none' => '"field":"Type","value":"None"'],
        'ssd_ps5' => ['yes' => '"field":"PS5 Compatible","value":"Yes"', 'no' => '"field":"PS5 Compatible","value":"No"'],
        'ssd_market' => ['consumer' => '"field":"Market","value":"Consumer"', 'enterprise' => '"field":"Market","value":"Enterprise"'],
        'ssd_status' => ['active' => '"field":"Production","value":"Active"', 'eol' => '"field":"Production","value":"End-of-life"'],
    ];
    foreach ($filters as $query_var => $options) {
        $selected = sanitize_key((string) get_query_var($query_var));
        if ($selected && isset($options[$selected])) {
            $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} ssd_filter WHERE ssd_filter.post_id={$wpdb->posts}.ID AND ssd_filter.meta_key='_tech_specs' AND ssd_filter.meta_value LIKE %s)", '%' . $wpdb->esc_like($options[$selected]) . '%');
        }
    }
    foreach (['ssd_min_read' => 'Sequential Read', 'ssd_min_tbw' => 'Endurance'] as $query_var => $field) {
        $minimum = absint(get_query_var($query_var));
        if ($minimum) {
            $pattern = '"field":"' . $field . '","value":"[0-9,]+';
            $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} ssd_num WHERE ssd_num.post_id={$wpdb->posts}.ID AND ssd_num.meta_key='_tech_specs' AND CAST(REPLACE(REGEXP_REPLACE(REGEXP_SUBSTR(ssd_num.meta_value, %s), '^.*value.:.', ''), ',', '') AS UNSIGNED) >= %d)", $pattern, $minimum);
        }
    }
    if (sanitize_key((string) get_query_var('ssd_landing')) === 'high-endurance') {
        $pattern = '"field":"Endurance","value":"[0-9,]+';
        $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} ssd_endurance WHERE ssd_endurance.post_id={$wpdb->posts}.ID AND ssd_endurance.meta_key='_tech_specs' AND CAST(REPLACE(REGEXP_REPLACE(REGEXP_SUBSTR(ssd_endurance.meta_value, %s), '^.*value.:.', ''), ',', '') AS UNSIGNED) >= 1000)", $pattern);
    }
    return $where;
}
add_filter('posts_where', 'ps_ssd_advanced_filter_where', 20, 2);

add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'tech_brand';
    $vars[] = 'catalog_sort';
    $vars[] = 'catalog_q';
    $vars[] = 'catalog_year';
    $vars[] = 'min_score';
    $vars[] = 'search_type';
    $vars[] = 'pc_ranked_catalog_search';
    $vars[] = 'ssd_landing';
    foreach (['ssd_capacity', 'ssd_interface', 'ssd_nand', 'ssd_cache', 'ssd_ps5', 'ssd_market', 'ssd_min_read', 'ssd_min_tbw', 'ssd_status'] as $var) $vars[] = $var;
    return $vars;
});

function ps_apply_catalog_filters(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    $is_phone = $query->is_post_type_archive('phone') || $query->is_tax('phone_brand');
    $is_hardware = $query->is_post_type_archive(['laptop', 'cpu', 'gpu', 'ssd']);
    if (!$is_phone && !$is_hardware) {
        return;
    }

    if ($is_phone) {
        $year = absint(get_query_var('catalog_year'));
        if ($year) {
            $tax_query = (array) $query->get('tax_query');
            $tax_query[] = [
                'taxonomy' => 'phone_year',
                'field' => 'slug',
                'terms' => (string) $year,
            ];
            $query->set('tax_query', $tax_query);
        }
    } else {
        $minimum = absint(get_query_var('min_score'));
        if ($minimum) {
            $meta_query = (array) $query->get('meta_query');
            $meta_query[] = [
                'key' => '_tech_score',
                'value' => $minimum,
                'compare' => '>=',
                'type' => 'NUMERIC',
            ];
            $query->set('meta_query', $meta_query);
        }
    }
}
add_action('pre_get_posts', 'ps_apply_catalog_filters', 46);

function ps_filter_catalog_title_search(string $where, WP_Query $query): string
{
    if (is_admin() || !$query->is_main_query()) {
        return $where;
    }
    $keyword = sanitize_text_field((string) get_query_var('catalog_q'));
    if ($keyword === '') {
        return $where;
    }
    global $wpdb;
    $like = '%' . $wpdb->esc_like($keyword) . '%';
    return $where . $wpdb->prepare(
        " AND ({$wpdb->posts}.post_title LIKE %s OR EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pc_alias
            WHERE pc_alias.post_id={$wpdb->posts}.ID
              AND pc_alias.meta_key='_pc_search_aliases'
              AND pc_alias.meta_value LIKE %s
        ))",
        $like,
        $like
    );
}
add_filter('posts_where', 'ps_filter_catalog_title_search', 10, 2);

function ps_expand_global_catalog_search(string $search, WP_Query $query): string
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return $search;
    }
    return $query->get('pc_ranked_catalog_search') ? '' : $search;
}
add_filter('posts_search', 'ps_expand_global_catalog_search', 30, 2);

function ps_apply_catalog_sorting(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    $sort = sanitize_key((string) get_query_var('catalog_sort'));
    $post_type = (string) $query->get('post_type');
    $is_phone = $query->is_post_type_archive('phone') || $query->is_tax('phone_brand');
    $is_hardware = $query->is_post_type_archive(['laptop', 'cpu', 'gpu', 'ssd']);
    if (!$is_phone && !$is_hardware) {
        return;
    }

    if ($is_phone) {
        if ($sort === 'oldest') {
            $query->set('meta_key', '_catalog_release_date');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'ASC');
        } elseif ($sort === 'name_asc' || $sort === 'name_desc') {
            $query->set('meta_key', '');
            $query->set('orderby', 'title');
            $query->set('order', $sort === 'name_desc' ? 'DESC' : 'ASC');
        } elseif ($sort === 'popular') {
            $query->set('meta_key', '_pc_popularity');
            $query->set('orderby', 'meta_value_num');
            $query->set('order', 'DESC');
        }
        return;
    }

    if ($sort === 'oldest') {
        $query->set('meta_key', '_catalog_release_date');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');
    } elseif ($sort === 'name_asc' || $sort === 'name_desc') {
        $query->set('meta_key', '');
        $query->set('orderby', 'title');
        $query->set('order', $sort === 'name_desc' ? 'DESC' : 'ASC');
    } elseif ($sort === 'score_desc' || $sort === 'score_asc') {
        $query->set('meta_key', '_tech_score');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', $sort === 'score_asc' ? 'ASC' : 'DESC');
    }
}
add_action('pre_get_posts', 'ps_apply_catalog_sorting', 45);

function ps_catalog_brands(string $post_type): array
{
    global $wpdb;
    $cache_key = 'ps_catalog_brands_' . sanitize_key($post_type);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }
    if ($post_type === 'phone') {
        $result = [];
        foreach (['Samsung', 'Apple', 'Google', 'Xiaomi', 'Huawei', 'LG'] as $name) {
            $term = get_term_by('name', $name, 'phone_brand');
            if ($term && !is_wp_error($term)) {
                $result[] = ['name' => $term->name, 'url' => get_term_link($term), 'count' => (int) $term->count];
            }
        }
        set_transient($cache_key, $result, 300);
        return $result;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT t.name, t.slug, COUNT(DISTINCT p.ID) AS product_count
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
         INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'hardware_brand'
         INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
         WHERE p.post_type = %s AND p.post_status = 'publish'
         GROUP BY t.term_id, t.name, t.slug
         ORDER BY product_count DESC, t.name ASC",
        $post_type
    ));
    $result = array_map(static fn($row) => [
        'name' => $row->name,
        'count' => (int) $row->product_count,
        'url' => function_exists('pc_hardware_brand_url')
            ? pc_hardware_brand_url($post_type, $row->slug)
            : add_query_arg('tech_brand', $row->slug, ps_catalog_archive_url($post_type)),
    ], $rows);
    set_transient($cache_key, $result, 300);
    return $result;
}

function ps_anonymous_frontend_cache_headers(): void
{
    if (
        is_admin()
        || is_user_logged_in()
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
        || (defined('REST_REQUEST') && REST_REQUEST)
    ) {
        return;
    }
    header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
}
add_action('send_headers', 'ps_anonymous_frontend_cache_headers');

function ps_category_brand_filter(string $post_type): void
{
    $brands = ps_catalog_brands($post_type);
    if (!$brands) {
        return;
    }
    $active = $post_type === 'phone' && is_tax('phone_brand')
        ? (string) get_queried_object()->name
        : ucwords(str_replace('-', ' ', sanitize_text_field((string) get_query_var('tech_brand'))));
    ?>
    <nav class="category-brand-filter" aria-label="<?php echo esc_attr(strtoupper($post_type)); ?> 브랜드 선택" data-brand-filter>
        <div class="category-brand-filter__label">
            <span>브랜드 필터</span>
            <strong>브랜드 선택</strong>
        </div>
        <button class="category-brand-filter__toggle" type="button" aria-expanded="false" data-brand-filter-toggle>
            <span><?php echo esc_html($active ? pc_apply_name_mappings($active) : '전체 브랜드'); ?></span>
            <small>변경</small>
            <i aria-hidden="true"></i>
        </button>
        <div class="category-brand-filter__options" data-brand-filter-options>
            <a class="<?php echo $active === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url(ps_catalog_archive_url($post_type)); ?>"<?php echo $active === '' ? ' aria-current="page"' : ''; ?>>전체</a>
            <?php foreach ($brands as $brand) : ?>
                <?php $selected = strcasecmp($active, $brand['name']) === 0; ?>
                <a class="<?php echo $selected ? 'is-active' : ''; ?>" href="<?php echo esc_url($brand['url']); ?>"<?php echo $selected ? ' aria-current="page"' : ''; ?>>
                    <?php echo esc_html($post_type === 'phone' ? pc_apply_name_mappings($brand['name']) : $brand['name']); ?><small><?php echo esc_html(number_format_i18n($brand['count'])); ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
}

function ps_catalog_sorting_controls(string $post_type): void
{
    $current = sanitize_key((string) get_query_var('catalog_sort'));
    $is_phone = $post_type === 'phone';
    $options = $is_phone
        ? ['' => '최신순', 'oldest' => '오래된순', 'popular' => '인기순', 'name_asc' => '이름 A–Z', 'name_desc' => '이름 Z–A']
        : ['' => '최신순', 'oldest' => '오래된순', 'name_asc' => '이름 A–Z', 'name_desc' => '이름 Z–A', 'score_desc' => '평가 높은순', 'score_asc' => '평가 낮은순'];
    $action = $is_phone && is_tax('phone_brand')
        ? get_term_link(get_queried_object())
        : ps_catalog_archive_url($post_type);
    ?>
    <form class="catalog-sort" method="get" action="<?php echo esc_url($action); ?>">
        <label for="catalog-sort-<?php echo esc_attr($post_type); ?>">
            <span>정렬</span>
            <strong>정렬</strong>
        </label>
        <?php if (!$is_phone && get_query_var('tech_brand')) : ?>
            <input type="hidden" name="tech_brand" value="<?php echo esc_attr(get_query_var('tech_brand')); ?>">
        <?php endif; ?>
        <?php if (get_query_var('catalog_q')) : ?><input type="hidden" name="catalog_q" value="<?php echo esc_attr(get_query_var('catalog_q')); ?>"><?php endif; ?>
        <?php if ($is_phone && get_query_var('catalog_year')) : ?><input type="hidden" name="catalog_year" value="<?php echo esc_attr(get_query_var('catalog_year')); ?>"><?php endif; ?>
        <?php if (!$is_phone && get_query_var('min_score')) : ?><input type="hidden" name="min_score" value="<?php echo esc_attr(get_query_var('min_score')); ?>"><?php endif; ?>
        <?php if ($post_type === 'ssd') foreach (['ssd_capacity', 'ssd_interface', 'ssd_nand', 'ssd_cache', 'ssd_ps5', 'ssd_market', 'ssd_min_read', 'ssd_min_tbw', 'ssd_status'] as $ssd_var) if (get_query_var($ssd_var)) : ?><input type="hidden" name="<?php echo esc_attr($ssd_var); ?>" value="<?php echo esc_attr(get_query_var($ssd_var)); ?>"><?php endif; ?>
        <div class="catalog-sort__select">
            <select id="catalog-sort-<?php echo esc_attr($post_type); ?>" name="catalog_sort" onchange="this.form.submit()">
                <?php foreach ($options as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit">적용</button></noscript>
    </form>
    <?php
}

function ps_catalog_filter_controls(string $post_type): void
{
    $is_phone = $post_type === 'phone';
    $action = $is_phone && is_tax('phone_brand')
        ? get_term_link(get_queried_object())
        : ps_catalog_archive_url($post_type);
    $years = $is_phone ? get_terms([
        'taxonomy' => 'phone_year',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'DESC',
    ]) : [];
    ?>
    <form class="catalog-filter" method="get" action="<?php echo esc_url($action); ?>">
        <?php if (!$is_phone && get_query_var('tech_brand')) : ?><input type="hidden" name="tech_brand" value="<?php echo esc_attr(get_query_var('tech_brand')); ?>"><?php endif; ?>
        <?php if (get_query_var('catalog_sort')) : ?><input type="hidden" name="catalog_sort" value="<?php echo esc_attr(get_query_var('catalog_sort')); ?>"><?php endif; ?>
        <label class="catalog-filter__keyword">
            <span>제품명</span>
            <input type="search" name="catalog_q" value="<?php echo esc_attr(get_query_var('catalog_q')); ?>" placeholder="제품명 검색">
        </label>
        <?php if ($is_phone) : ?>
            <label>
                <span>출시 연도</span>
                <div class="catalog-filter__select">
                <select name="catalog_year">
                    <option value="">전체 연도</option>
                    <?php foreach ($years as $year) : ?>
                        <option value="<?php echo esc_attr($year->slug); ?>" <?php selected((string) get_query_var('catalog_year'), $year->slug); ?>><?php echo esc_html($year->name); ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
            </label>
        <?php else : ?>
            <?php if ($post_type === 'ssd') : ?>
                <?php
                $ssd_filters = [
                    'ssd_capacity' => ['용량', ['' => '전체 용량', '1tb' => '1TB', '2tb' => '2TB', '4tb' => '4TB']],
                    'ssd_interface' => ['인터페이스', ['' => '전체 규격', 'pcie3' => 'PCIe 3.0', 'pcie4' => 'PCIe 4.0', 'pcie5' => 'PCIe 5.0', 'sata' => 'SATA']],
                    'ssd_nand' => ['NAND', ['' => '전체 NAND', 'tlc' => 'TLC', 'qlc' => 'QLC']],
                    'ssd_cache' => ['캐시', ['' => '전체 캐시', 'dram' => 'DRAM', 'hmb' => 'HMB', 'none' => 'DRAM 없음']],
                    'ssd_market' => ['용도', ['' => '전체 시장', 'consumer' => '소비자용', 'enterprise' => '기업용']],
                    'ssd_ps5' => ['PS5', ['' => '전체', 'yes' => '호환', 'no' => '비호환']],
                    'ssd_status' => ['생산 상태', ['' => '전체 상태', 'active' => '생산 중', 'eol' => '단종']],
                ];
                ?>
                <?php foreach ($ssd_filters as $filter_name => [$filter_label, $filter_options]) : ?>
                    <label><span><?php echo esc_html($filter_label); ?></span><div class="catalog-filter__select"><select name="<?php echo esc_attr($filter_name); ?>">
                        <?php foreach ($filter_options as $value => $option_label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string) get_query_var($filter_name), $value); ?>><?php echo esc_html($option_label); ?></option><?php endforeach; ?>
                    </select></div></label>
                <?php endforeach; ?>
                <label><span>최소 읽기</span><div class="catalog-filter__select"><select name="ssd_min_read"><option value="">전체 속도</option><?php foreach ([500, 3000, 5000, 7000, 10000] as $speed) : ?><option value="<?php echo $speed; ?>" <?php selected((int) get_query_var('ssd_min_read'), $speed); ?>><?php echo number_format_i18n($speed); ?> MB/s 이상</option><?php endforeach; ?></select></div></label>
                <label><span>최소 내구성</span><div class="catalog-filter__select"><select name="ssd_min_tbw"><option value="">전체 내구성</option><?php foreach ([300, 600, 1000, 3000] as $tbw) : ?><option value="<?php echo $tbw; ?>" <?php selected((int) get_query_var('ssd_min_tbw'), $tbw); ?>><?php echo number_format_i18n($tbw); ?> TBW 이상</option><?php endforeach; ?></select></div></label>
            <?php else : ?>
                <label><span>최소 평가</span><div class="catalog-filter__select"><select name="min_score"><option value="">전체 점수</option><?php foreach ([50, 70, 85] as $score) : ?><option value="<?php echo esc_attr($score); ?>" <?php selected((int) get_query_var('min_score'), $score); ?>><?php echo esc_html($score); ?>점 이상</option><?php endforeach; ?></select></div></label>
            <?php endif; ?>
        <?php endif; ?>
        <button type="submit">필터 적용</button>
        <?php if (pc_catalog_filter_active()) : ?>
            <a href="<?php echo esc_url($action); ?>">초기화</a>
        <?php endif; ?>
    </form>
    <?php
}

function ps_catalog_tools(string $post_type): void
{
    $has_active_tools = (bool) (
        get_query_var('catalog_q')
        || get_query_var('catalog_year')
        || get_query_var('min_score')
        || get_query_var('catalog_sort')
        || ($post_type === 'ssd' && pc_catalog_filter_active())
    );
    $panel_id = 'catalog-tools-' . sanitize_html_class($post_type);
    echo '<div class="catalog-tools" data-catalog-tools>';
    echo '<button class="catalog-tools__toggle' . ($has_active_tools ? ' has-active-filter' : '') . '" type="button" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '" data-catalog-tools-toggle>';
    echo '<span>필터·정렬</span>';
    echo $has_active_tools ? '<small>적용 중</small>' : '<small>조건 선택</small>';
    echo '<i aria-hidden="true"></i></button>';
    echo '<div class="catalog-tools__panel" id="' . esc_attr($panel_id) . '" data-catalog-tools-panel>';
    ps_catalog_filter_controls($post_type);
    ps_catalog_sorting_controls($post_type);
    echo '</div>';
    ps_catalog_view_switcher($post_type);
    echo '</div>';
}

function ps_catalog_view_switcher(string $post_type): void
{
    ?>
    <div class="catalog-view-switcher" data-catalog-view-switcher data-view-key="<?php echo esc_attr($post_type); ?>">
        <button type="button" data-catalog-view-toggle aria-label="간략 보기로 전환" title="간략 보기로 전환">
            <i aria-hidden="true"></i>
        </button>
    </div>
    <?php
}

function ps_catalog_display_date(int $post_id, string $fallback = ''): string
{
    $normalized = trim((string) get_post_meta($post_id, '_catalog_release_date', true));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
        return $normalized;
    }
    $fallback = trim($fallback);
    if (!$fallback) {
        return '';
    }
    if (preg_match('/\b((?:19|20)\d{2})-(\d{2})-(\d{2})\b/', $fallback, $match)) {
        return $match[1] . '-' . $match[2] . '-' . $match[3];
    }
    if (preg_match(
        '/(?:\b(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2}\b|\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\b)/i',
        $fallback
    )) {
        $timestamp = strtotime($fallback);
        if ($timestamp !== false) {
            return wp_date('Y-m-d', $timestamp);
        }
    }
    return '';
}

function ps_phone_card(?WP_Post $post = null, int $rank = 0): void
{
    $post = $post ?: get_post();
    $device = function_exists('pc_get_device') ? pc_get_device((int) $post->ID) : null;
    $display_name = pc_product_name((int) $post->ID);
    $display_brand = $device?->brand ? pc_apply_name_mappings((string) $device->brand) : '스마트폰';
    $display_date = ps_catalog_display_date((int) $post->ID, (string) ($device?->announced ?? ''));
    ?>
    <article class="phone-card">
        <span class="rank"><?php echo esc_html(str_pad((string) $rank, 2, '0', STR_PAD_LEFT)); ?></span>
        <?php if (pc_public_image_url($device)) : ?>
            <img loading="lazy" fetchpriority="low" decoding="async" width="300" height="220" src="<?php echo esc_url(pc_public_image_url($device)); ?>" alt="<?php echo esc_attr($display_name); ?>">
        <?php endif; ?>
        <p class="phone-card__brand"><?php echo esc_html($display_brand); ?></p>
        <h3><?php echo esc_html($display_name); ?></h3>
        <p class="phone-card__date"><?php echo esc_html($display_date ?: '날짜 미상'); ?></p>
        <a href="<?php echo esc_url(get_permalink($post)); ?>"<?php echo is_search() ? ' data-track-event="search_click" data-track-post="' . esc_attr((string) $post->ID) . '"' : ''; ?>><?php echo esc_html($display_name); ?> 보기</a>
    </article>
    <?php
}

function ps_tech_card(?WP_Post $post = null, int $rank = 0): void
{
    $post = $post ?: get_post();
    $type = get_post_type($post);
    $labels = ['laptop' => '노트북', 'cpu' => '프로세서', 'gpu' => '그래픽카드', 'ssd' => 'SSD'];
    $score = get_post_meta($post->ID, '_tech_score', true);
    $display_date = ps_catalog_display_date((int) $post->ID, (string) get_post_meta($post->ID, '_tech_launched', true));
    ?>
    <article class="tech-card">
        <div class="tech-card__meta">
            <span><?php echo esc_html(str_pad((string) $rank, 2, '0', STR_PAD_LEFT)); ?></span>
            <small><?php echo esc_html($labels[$type] ?? strtoupper((string) $type)); ?></small>
        </div>
        <?php if ($type === 'ssd') : $ssd_status = ps_ssd_product_status((int) $post->ID); ?><span class="ssd-status ssd-status--<?php echo esc_attr($ssd_status['key']); ?>"><?php echo esc_html($ssd_status['label']); ?></span><?php endif; ?>
        <?php $image_url = function_exists('pc_public_tech_image_url') ? pc_public_tech_image_url((int) $post->ID) : null; ?>
        <?php if ($type === 'ssd') : ?>
            <?php ps_ssd_vector_mark('ssd-vector--card'); ?>
        <?php elseif ($image_url) : ?>
            <div class="tech-card__image"><img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($post->post_title); ?>" width="300" height="150" loading="lazy" fetchpriority="low" decoding="async" referrerpolicy="no-referrer"></div>
        <?php elseif (has_post_thumbnail($post)) : ?>
            <?php echo get_the_post_thumbnail($post, 'medium', ['loading' => 'lazy', 'fetchpriority' => 'low', 'decoding' => 'async']); ?>
        <?php else : ?>
            <div class="tech-card__glyph" aria-hidden="true"><?php echo esc_html($type === 'laptop' ? '▱' : ($type === 'cpu' ? '◇' : '▰')); ?></div>
        <?php endif; ?>
        <div>
            <h3><?php echo esc_html($post->post_title); ?></h3>
            <p><?php echo esc_html(get_the_excerpt($post) ?: '상세 사양과 성능 데이터를 확인하세요.'); ?></p>
            <?php if ($display_date) : ?><time class="tech-card__date" datetime="<?php echo esc_attr($display_date); ?>"><?php echo esc_html($display_date); ?></time><?php endif; ?>
        </div>
        <?php if ($score !== '') : ?><strong class="tech-card__score"><?php echo esc_html($score); ?><small>/100</small></strong><?php endif; ?>
        <a href="<?php echo esc_url(get_permalink($post)); ?>"<?php echo is_search() ? ' data-track-event="search_click" data-track-post="' . esc_attr((string) $post->ID) . '"' : ''; ?>><?php echo esc_html($post->post_title); ?> 보기</a>
    </article>
    <?php
}

function ps_ssd_vector_mark(string $class = ''): void
{
    ?>
    <div class="ssd-vector <?php echo esc_attr($class); ?>" role="img" aria-label="SSD 제품 이미지 대체 그래픽">
        <span>SSD</span>
        <small>SOLID STATE DRIVE</small>
        <i aria-hidden="true"></i>
    </div>
    <?php
}

function ps_ssd_insights(array $specs): array
{
    $values = [];
    foreach ($specs as $spec) {
        $field = trim((string) ($spec['field'] ?? ''));
        if ($field !== '' && !isset($values[$field])) $values[$field] = trim((string) ($spec['value'] ?? ''));
    }
    $number = static function (string $field) use ($values): float {
        return isset($values[$field]) && preg_match('/[\d,.]+/', $values[$field], $match)
            ? (float) str_replace(',', '', $match[0]) : 0;
    };
    $read = $number('Sequential Read');
    $write = $number('Sequential Write');
    $interface = $values['Interface'] ?? '정보 없음';
    $nand = $values['Technology'] ?? ($values['Type'] ?? '정보 없음');
    $advantages = [];
    $cautions = [];
    $uses = [];

    if ($read >= 5000) $advantages[] = '순차 읽기 ' . $values['Sequential Read'] . '로 대용량 파일과 고사양 작업에 유리합니다.';
    elseif ($read >= 3000) $advantages[] = '순차 읽기 ' . $values['Sequential Read'] . '로 NVMe 기반 게임과 일반 작업에 충분한 성능입니다.';
    elseif ($read >= 500) $advantages[] = 'SATA 인터페이스에서 기대할 수 있는 상위권 순차 읽기 속도를 제공합니다.';
    if ($write > 0) $advantages[] = '순차 쓰기 ' . $values['Sequential Write'] . ' 사양으로 파일 복사 성능을 가늠할 수 있습니다.';
    if (!empty($values['Endurance']) && strcasecmp($values['Endurance'], 'Unknown') !== 0) $advantages[] = '쓰기 내구성은 ' . $values['Endurance'] . '로 명시되어 있습니다.';
    if (($values['TRIM'] ?? '') === 'Yes') $advantages[] = 'TRIM을 지원해 장기간 사용 시 저장장치 관리에 유리합니다.';

    if (($values['Warranty'] ?? '') === 'Unknown') $cautions[] = '보증 기간 정보가 없어 구매처 또는 제조사 확인이 필요합니다.';
    if (($values['Random Read'] ?? '') === 'Unknown' || ($values['Random Write'] ?? '') === 'Unknown') $cautions[] = '랜덤 읽기·쓰기 정보가 없어 체감 반응성을 수치만으로 판단하기 어렵습니다.';
    if (($values['Power Loss Protection'] ?? '') === 'No') $cautions[] = '전원 손실 보호 기능을 지원하지 않는 것으로 표시됩니다.';
    if (($values['Type'] ?? '') === 'None') $cautions[] = '별도 DRAM 캐시가 없는 구성으로 확인됩니다.';

    if (stripos($interface, 'PCIe') !== false || stripos($interface, 'NVMe') !== false) $uses[] = '게임용 PC와 빠른 부팅·앱 실행이 필요한 시스템';
    if (stripos($interface, 'SATA') !== false) $uses[] = '기존 SATA 노트북·데스크톱의 저장장치 교체';
    if ($read >= 5000 && $write >= 4000) $uses[] = '대용량 영상·사진 편집과 작업 파일 이동';
    if (($values['PS5 Compatible'] ?? '') === 'Yes') $uses[] = 'PlayStation 5 저장공간 확장';
    if (!$uses) $uses[] = '일반 문서 작업과 운영체제·프로그램 저장용';

    return [
        'advantages' => array_slice(array_values(array_unique($advantages ?: ['용량과 인터페이스, 성능 정보를 한 페이지에서 확인할 수 있습니다.'])), 0, 3),
        'cautions' => array_slice(array_values(array_unique($cautions ?: ['실제 성능은 시스템 구성과 작업 환경에 따라 달라질 수 있습니다.'])), 0, 3),
        'uses' => array_slice(array_values(array_unique($uses)), 0, 3),
        'interface' => $interface,
        'nand' => $nand,
    ];
}

function ps_ssd_specs(int $post_id): array
{
    $raw = get_post_meta($post_id, '_tech_specs', true);
    if (is_array($raw)) return $raw;
    $decoded = json_decode((string) $raw, true);
    if (is_string($decoded)) $decoded = json_decode($decoded, true);
    return is_array($decoded) ? $decoded : [];
}

function ps_ssd_spec_values(int $post_id): array
{
    $values = [];
    foreach (ps_ssd_specs($post_id) as $spec) {
        $field = trim((string) ($spec['field'] ?? ''));
        $value = trim((string) ($spec['value'] ?? ''));
        if ($field !== '') $values[$field][] = $value;
    }
    return $values;
}

function ps_ssd_scorecard(int $post_id): array
{
    $values = ps_ssd_spec_values($post_id);
    $first = static fn(string $key): string => (string) ($values[$key][0] ?? '');
    $number = static function (string $key) use ($first): ?float {
        $value = $first($key);
        return $value !== '' && preg_match('/[\d,.]+/', $value, $match)
            ? (float) str_replace(',', '', $match[0]) : null;
    };
    $scale = static fn(?float $value, float $maximum): ?int => $value === null
        ? null : (int) round(max(0, min(100, ($value / $maximum) * 100)));
    $average = static function (array $scores): ?int {
        $scores = array_values(array_filter($scores, static fn($score): bool => $score !== null));
        return $scores ? (int) round(array_sum($scores) / count($scores)) : null;
    };

    $read = $number('Sequential Read');
    $write = $number('Sequential Write');
    $random_read = $number('Random Read');
    $random_write = $number('Random Write');
    $capacity = $number('Capacity');
    $endurance = $number('Endurance');
    $interface = $first('Interface');
    $market = $first('Market');
    $power_text = $first('Power Draw');
    $power = preg_match('/([\d.]+)\s*W\s*\(Avg\)/i', $power_text, $power_match)
        ? (float) $power_match[1] : $number('Power Draw');
    $speed_ceiling = stripos($interface, 'PCIe 5') !== false ? [14000, 14000]
        : (stripos($interface, 'PCIe 4') !== false ? [7500, 7500]
        : (stripos($interface, 'PCIe 3') !== false ? [3500, 3500]
        : (stripos($interface, 'SATA') !== false ? [560, 540]
        : (stripos($interface, 'SAS') !== false ? [2400, 2200] : [14000, 12000]))));
    $controller_features = implode(' ', $values['Controller Features'] ?? []);
    $dram_type = implode(' ', $values['Type'] ?? []);
    $features = [
        strcasecmp($first('TRIM'), 'Yes') === 0 ? 90 : null,
        strcasecmp($first('SMART'), 'Yes') === 0 ? 90 : null,
        strcasecmp($first('Power Loss Protection'), 'Yes') === 0 ? 100 : 45,
        $first('Encryption') && strcasecmp($first('Encryption'), 'No') !== 0 ? 90 : null,
        stripos($controller_features, 'DRAM') !== false ? 100 : (stripos($controller_features, 'HMB') !== false ? 72 : (stripos($dram_type, 'None') !== false ? 45 : null)),
    ];
    $warranty = $number('Warranty');
    $tbw_per_tb = ($endurance !== null && $capacity) ? $endurance / max(0.12, $capacity >= 100 ? $capacity / 1000 : $capacity) : null;
    $enterprise = stripos($market, 'Enterprise') !== false;
    $categories = [
        'performance' => ['label' => '동급 성능', 'score' => $average([$scale($read, $speed_ceiling[0]), $scale($write, $speed_ceiling[1]), $scale($random_read, $enterprise ? 1000000 : 2000000), $scale($random_write, $enterprise ? 500000 : 1800000)])],
        'endurance' => ['label' => '내구성', 'score' => $average([$scale($tbw_per_tb, $enterprise ? 6000 : 1200), $scale($warranty, 5)])],
        'features' => ['label' => '기능', 'score' => $average($features)],
        'efficiency' => ['label' => '효율', 'score' => $power === null ? null : (int) round(max(15, min(100, 110 - ($power * 5))))],
    ];
    $weights = $enterprise
        ? ['performance' => 0.30, 'endurance' => 0.40, 'features' => 0.20, 'efficiency' => 0.10]
        : ['performance' => 0.45, 'endurance' => 0.25, 'features' => 0.20, 'efficiency' => 0.10];
    $weighted = 0.0;
    $used_weight = 0.0;
    foreach ($categories as $key => $category) {
        if ($category['score'] !== null) {
            $weighted += $category['score'] * $weights[$key];
            $used_weight += $weights[$key];
        }
    }
    $overall = $used_weight >= 0.45 ? (int) round($weighted / $used_weight) : null;
    $coverage = (int) round((count(array_filter([$read, $write, $random_read, $random_write, $endurance, $warranty, $power], static fn($v): bool => $v !== null)) / 7) * 100);
    return compact('overall', 'categories', 'coverage', 'interface', 'market') + ['values' => $values, 'version' => '2.0'];
}

function ps_ssd_product_status(int $post_id): array
{
    $values = ps_ssd_spec_values($post_id);
    $production = strtolower((string) ($values['Production'][0] ?? ''));
    $release = (string) get_post_meta($post_id, '_catalog_release_date', true);
    if ($release && strtotime($release) > current_time('timestamp')) return ['key' => 'upcoming', 'label' => '출시 예정'];
    if (str_contains($production, 'end-of-life') || str_contains($production, 'discontinued')) return ['key' => 'eol', 'label' => '단종'];
    if (str_contains($production, 'active')) return ['key' => 'active', 'label' => '생산 중'];
    if ($release && strtotime($release) < strtotime('-8 years', current_time('timestamp'))) return ['key' => 'legacy', 'label' => '레거시'];
    return ['key' => 'unknown', 'label' => '상태 미상'];
}

function ps_ssd_faqs(int $post_id, array $scorecard): array
{
    $name = get_the_title($post_id);
    $values = $scorecard['values'];
    $first = static fn(string $key): string => (string) ($values[$key][0] ?? '정보 없음');
    $ps5 = $first('PS5 Compatible');
    $interface = $first('Interface');
    $read = $first('Sequential Read');
    return [
        ['question' => $name . '의 인터페이스는 무엇인가요?', 'answer' => $interface === '정보 없음' ? '인터페이스 정보가 확인되지 않았습니다.' : $interface . ' 규격을 사용합니다. 장착할 시스템이 같은 규격을 지원하는지 확인해야 합니다.'],
        ['question' => $name . '의 읽기 속도는 어느 정도인가요?', 'answer' => $read === '정보 없음' ? '공개된 순차 읽기 속도를 확인할 수 없습니다.' : '표기된 순차 읽기 속도는 ' . $read . '입니다. 실제 속도는 시스템과 작업 유형에 따라 달라질 수 있습니다.'],
        ['question' => 'PlayStation 5에서 사용할 수 있나요?', 'answer' => strcasecmp($ps5, 'Yes') === 0 ? '수집된 사양에는 PS5 호환 제품으로 표시되어 있습니다. 장착 공간과 방열판 조건도 함께 확인하세요.' : (strcasecmp($ps5, 'No') === 0 ? '수집된 사양에는 PS5 비호환으로 표시되어 있습니다.' : 'PS5 호환 여부가 명시되지 않아 제조사의 최신 호환 정보를 확인해야 합니다.')],
        ['question' => '스펙매치 자체 점수는 어떻게 계산하나요?', 'answer' => '가격을 제외하고 공개된 속도, 내구성, 기능, 소비전력 항목만 동일한 규칙으로 환산합니다. 누락된 항목은 점수 계산에서 제외합니다.'],
    ];
}

function ps_related_tech_posts(int $post_id, string $post_type, int $limit = 4): array
{
    if (!in_array($post_type, ['laptop', 'cpu', 'gpu', 'ssd'], true)) {
        return [];
    }

    if ($post_type === 'ssd') {
        $cache_key = 'ps_ssd_related_' . $post_id . '_' . $limit;
        $cached_ids = get_transient($cache_key);
        if (is_array($cached_ids)) {
            return array_values(array_filter(array_map('get_post', array_map('intval', $cached_ids))));
        }
        $source = ps_ssd_spec_values($post_id);
        $source_capacity = (string) ($source['Capacity'][0] ?? '');
        $source_interface = (string) ($source['Interface'][0] ?? '');
        $source_brands = wp_get_post_terms($post_id, 'hardware_brand', ['fields' => 'ids']);
        $source_brand = !is_wp_error($source_brands) ? (int) ($source_brands[0] ?? 0) : 0;
        $candidates = get_posts([
            'post_type' => 'ssd', 'post_status' => 'publish', 'post__not_in' => [$post_id],
            'posts_per_page' => 40, 'meta_key' => '_catalog_release_date',
            'orderby' => ['meta_value' => 'DESC', 'date' => 'DESC'], 'order' => 'DESC', 'no_found_rows' => true,
        ]);
        $ranked = [];
        foreach ($candidates as $candidate) {
            $candidate_values = ps_ssd_spec_values((int) $candidate->ID);
            $candidate_brands = wp_get_post_terms($candidate->ID, 'hardware_brand', ['fields' => 'ids']);
            $score = 0;
            if ($source_capacity && $source_capacity === (string) ($candidate_values['Capacity'][0] ?? '')) $score += 45;
            if ($source_interface && $source_interface === (string) ($candidate_values['Interface'][0] ?? '')) $score += 30;
            if ($source_brand && !is_wp_error($candidate_brands) && in_array($source_brand, array_map('intval', $candidate_brands), true)) $score += 20;
            $score += min(5, strtotime((string) get_post_meta($candidate->ID, '_catalog_release_date', true)) / 1000000000);
            $ranked[] = ['post' => $candidate, 'score' => $score];
        }
        usort($ranked, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $related = array_map(static fn(array $item): WP_Post => $item['post'], array_slice($ranked, 0, $limit));
        set_transient($cache_key, wp_list_pluck($related, 'ID'), 12 * HOUR_IN_SECONDS);
        return $related;
    }

    $term_ids = wp_get_post_terms($post_id, 'hardware_brand', ['fields' => 'ids']);
    $query_args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'post__not_in' => [$post_id],
        'posts_per_page' => $limit,
        'meta_key' => '_catalog_release_date',
        'orderby' => ['meta_value' => 'DESC', 'date' => 'DESC'],
        'order' => 'DESC',
        'no_found_rows' => true,
    ];
    if (!is_wp_error($term_ids) && $term_ids) {
        $query_args['tax_query'] = [[
            'taxonomy' => 'hardware_brand',
            'field' => 'term_id',
            'terms' => array_map('intval', $term_ids),
        ]];
    }

    $related = get_posts($query_args);
    if (count($related) >= $limit) {
        return $related;
    }

    $exclude = array_merge([$post_id], wp_list_pluck($related, 'ID'));
    $fallback = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'post__not_in' => array_map('intval', $exclude),
        'posts_per_page' => $limit - count($related),
        'meta_key' => '_catalog_release_date',
        'orderby' => ['meta_value' => 'DESC', 'date' => 'DESC'],
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);

    return array_merge($related, $fallback);
}

function ps_adjacent_catalog_posts(int $post_id): array
{
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu', 'ssd'], true)) {
        return [];
    }

    $release_date = (string) get_post_meta($post_id, '_catalog_release_date', true);
    if (!$release_date) {
        return [];
    }

    $taxonomy = $post_type === 'phone' ? 'phone_brand' : 'hardware_brand';
    $term_ids = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);
    $base = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'post__not_in' => [$post_id],
        'posts_per_page' => 1,
        'meta_key' => '_catalog_release_date',
        'no_found_rows' => true,
    ];
    if (!is_wp_error($term_ids) && $term_ids) {
        $base['tax_query'] = [[
            'taxonomy' => $taxonomy,
            'field' => 'term_id',
            'terms' => array_map('intval', $term_ids),
        ]];
    }

    $previous = get_posts(array_merge($base, [
        'meta_value' => $release_date,
        'meta_compare' => '<',
        'meta_type' => 'DATE',
        'orderby' => 'meta_value',
        'order' => 'DESC',
    ]));
    $next = get_posts(array_merge($base, [
        'meta_value' => $release_date,
        'meta_compare' => '>',
        'meta_type' => 'DATE',
        'orderby' => 'meta_value',
        'order' => 'ASC',
    ]));

    return [
        'previous' => $previous[0] ?? null,
        'next' => $next[0] ?? null,
    ];
}

function ps_product_connections(int $post_id): void
{
    $adjacent = ps_adjacent_catalog_posts($post_id);
    if (empty($adjacent['previous']) && empty($adjacent['next'])) {
        return;
    }
    ?>
    <nav class="product-connections" aria-label="이전 세대와 후속 세대 제품">
        <?php if (!empty($adjacent['previous'])) : ?>
            <a class="product-connection--previous" href="<?php echo esc_url(get_permalink($adjacent['previous'])); ?>">
                <span>이전 출시 제품</span>
                <strong><?php echo esc_html(get_the_title($adjacent['previous'])); ?></strong>
                <small>← 자세히 보기</small>
            </a>
        <?php else : ?><span></span><?php endif; ?>
        <?php if (!empty($adjacent['next'])) : ?>
            <a class="product-connection--next" href="<?php echo esc_url(get_permalink($adjacent['next'])); ?>">
                <span>후속 출시 제품</span>
                <strong><?php echo esc_html(get_the_title($adjacent['next'])); ?></strong>
                <small>자세히 보기 →</small>
            </a>
        <?php endif; ?>
    </nav>
    <?php
}

function ps_product_series_link(int $post_id): void
{
    $slug = (string) get_post_meta($post_id, '_catalog_series_slug', true);
    $label = (string) get_post_meta($post_id, '_catalog_series_label', true);
    $type = (string) get_post_type($post_id);
    if (!$slug || !$label || !function_exists('pc_series_url')) {
        return;
    }
    ?>
    <a class="product-series-link" href="<?php echo esc_url(pc_series_url($type, $slug)); ?>">
        <span>같은 시리즈</span>
        <strong><?php echo esc_html($label); ?></strong>
        <i>전체 보기 →</i>
    </a>
    <?php
}

function ps_recent_products_section(int $post_id): void
{
    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu', 'ssd'], true)) {
        return;
    }
    $taxonomy = $post_type === 'phone' ? 'phone_brand' : 'hardware_brand';
    $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
    $image = '';
    if ($post_type === 'phone' && function_exists('pc_get_device') && function_exists('pc_public_image_url')) {
        $device = pc_get_device($post_id);
        $image = $device ? (string) pc_public_image_url($device) : '';
    } else {
        $image = function_exists('pc_public_tech_image_url') ? (string) pc_public_tech_image_url($post_id) : '';
    }
    $product = [
        'id' => $post_id,
        'name' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'image' => $image,
        'brand' => !is_wp_error($terms) ? ($terms[0] ?? '') : '',
        'type' => $post_type,
    ];
    ?>
    <section class="recent-products" data-recent-products data-current-product="<?php echo esc_attr(wp_json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>" hidden>
        <div class="section-heading">
            <div><p class="eyebrow">최근 열어본 제품</p><h2>최근 본 제품</h2></div>
        </div>
        <div class="recent-products__list" data-recent-list></div>
    </section>
    <?php
}

function ps_tech_compact_row(?WP_Post $post = null, int $rank = 0): void
{
    $post = $post ?: get_post();
    $type = (string) get_post_type($post);
    $brands = get_the_terms($post, 'hardware_brand');
    $brand = $brands && !is_wp_error($brands) ? $brands[0]->name : strtoupper($type);
    $score = get_post_meta($post->ID, '_tech_score', true);
    $launched = ps_catalog_display_date((int) $post->ID, (string) get_post_meta($post->ID, '_tech_launched', true));
    ?>
    <article class="tech-index-row">
        <span class="tech-index-row__rank"><?php echo esc_html(str_pad((string) $rank, 3, '0', STR_PAD_LEFT)); ?></span>
        <span class="tech-index-row__brand"><?php echo esc_html($brand); ?></span>
        <h2><a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html($post->post_title); ?></a></h2>
        <?php if ($launched) : ?><time datetime="<?php echo esc_attr($launched); ?>"><?php echo esc_html($launched); ?></time><?php endif; ?>
        <strong><?php echo $score !== '' ? esc_html($score) . '<small>/100</small>' : '<small>상세 보기</small>'; ?></strong>
    </article>
    <?php
}

function ps_home_catalog_list(string $post_type, string $title, string $eyebrow): void
{
    $args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => 6,
        'no_found_rows' => true,
    ];
    if ($post_type === 'phone') {
        $args = array_merge($args, pc_newest_query_args());
    } else {
        $args['meta_key'] = '_catalog_release_date';
        $args['orderby'] = ['meta_value' => 'DESC', 'title' => 'ASC'];
        $args['order'] = 'DESC';
    }
    $items = new WP_Query($args);
    ?>
    <section class="home-index-column home-index-column--<?php echo esc_attr($post_type); ?>">
        <header>
            <div><span><?php echo esc_html($eyebrow); ?></span><h3><?php echo esc_html($title); ?></h3></div>
            <a href="<?php echo esc_url(ps_catalog_archive_url($post_type)); ?>" aria-label="<?php echo esc_attr($title); ?> 전체 보기">전체</a>
        </header>
        <ol>
            <?php $rank = 1; while ($items->have_posts()) : $items->the_post(); ?>
                <?php
                $post_id = get_the_ID();
                if ($post_type === 'phone') {
                    $device = pc_get_device($post_id);
                    $brand = $device?->brand ?: '스마트폰';
                    $meta = $device?->release_year ?: '';
                } else {
                    $terms = get_the_terms($post_id, 'hardware_brand');
                    $brand = $terms && !is_wp_error($terms) ? $terms[0]->name : strtoupper($post_type);
                    $score = get_post_meta($post_id, '_tech_score', true);
                    $meta = $score !== '' ? $score . '/100' : '';
                }
                ?>
                <li>
                    <span><?php echo esc_html(str_pad((string) $rank++, 2, '0', STR_PAD_LEFT)); ?></span>
                    <a href="<?php the_permalink(); ?>"><small><?php echo esc_html($brand); ?></small><strong><?php the_title(); ?></strong></a>
                    <b><?php echo esc_html($meta); ?></b>
                </li>
            <?php endwhile; wp_reset_postdata(); ?>
        </ol>
    </section>
    <?php
}

function ps_catalog_count(string $post_type): int
{
    return (int) (wp_count_posts($post_type)->publish ?? 0);
}

function ps_catalog_archive_url(string $post_type): string
{
    return (string) (get_post_type_archive_link($post_type) ?: home_url('/'));
}

function ps_phone_sidebar_widget(string $title, string $type, array $posts): void
{
    if (!$posts) {
        return;
    }
    ?>
    <section class="phone-side-widget phone-side-widget--<?php echo esc_attr($type); ?>">
        <div class="side-widget-title">
            <span><?php echo $type === 'popular' ? '인기 순위' : '새로 등록됨'; ?></span>
            <h2><?php echo esc_html($title); ?></h2>
        </div>
        <ol>
            <?php foreach ($posts as $index => $post) :
                $device = pc_get_device((int) $post->ID);
            ?>
                <li>
                    <a href="<?php echo esc_url(get_permalink($post)); ?>">
                        <b><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></b>
                        <?php if (pc_public_image_url($device)) : ?>
                            <img loading="lazy" decoding="async" width="44" height="54" src="<?php echo esc_url(pc_public_image_url($device)); ?>" alt="">
                        <?php endif; ?>
                        <span>
                            <small><?php echo esc_html($device?->brand ? pc_apply_name_mappings((string) $device->brand) : '스마트폰'); ?></small>
                            <strong><?php echo esc_html(pc_product_name((int) $post->ID)); ?></strong>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php
}

function ps_tech_sidebar_posts(string $post_type, string $mode = 'newest', int $limit = 5, string $brand = ''): array
{
    if (!in_array($post_type, ['laptop', 'cpu', 'gpu', 'ssd'], true)) {
        return [];
    }
    $cache_key = 'ps_side_' . md5(implode('|', [$post_type, $mode, $limit, $brand]));
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_values(array_filter(array_map('get_post', $cached_ids)));
    }
    $args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'no_found_rows' => true,
    ];
    if ($mode === 'popular' && function_exists('pc_popular_posts_from_events')) {
        $measured = pc_popular_posts_from_events($post_type, $brand === '' ? $limit : 30);
        if ($brand !== '') {
            $measured = array_values(array_filter(
                $measured,
                static fn(WP_Post $post): bool => has_term(sanitize_title($brand), 'hardware_brand', $post)
            ));
            $measured = array_slice($measured, 0, $limit);
        }
        if ($measured) {
            set_transient($cache_key, wp_list_pluck($measured, 'ID'), HOUR_IN_SECONDS);
            return $measured;
        }
    }
    if ($brand !== '') {
        $args['tax_query'] = [[
            'taxonomy' => 'hardware_brand',
            'field' => 'slug',
            'terms' => sanitize_title($brand),
        ]];
    }
    if ($mode === 'popular') {
        $args['meta_key'] = '_tech_score';
        $args['orderby'] = ['meta_value_num' => 'DESC', 'date' => 'DESC'];
    } else {
        $args['meta_key'] = '_catalog_release_date';
        $args['orderby'] = ['meta_value' => 'DESC', 'date' => 'DESC'];
    }
    $posts = get_posts($args);
    set_transient($cache_key, wp_list_pluck($posts, 'ID'), 6 * HOUR_IN_SECONDS);
    return $posts;
}

function ps_tech_sidebar_widget(string $title, string $mode, string $post_type, array $posts): void
{
    if (!$posts) {
        return;
    }
    ?>
    <section class="phone-side-widget phone-side-widget--<?php echo esc_attr($mode); ?><?php echo $post_type === 'ssd' ? ' phone-side-widget--text-only' : ''; ?>">
        <div class="side-widget-title">
            <span><?php echo $mode === 'popular' ? '높은 평가' : '새로 등록됨'; ?></span>
            <h2><?php echo esc_html($title); ?></h2>
        </div>
        <ol>
            <?php foreach ($posts as $index => $post) :
                $brands = wp_get_post_terms($post->ID, 'hardware_brand', ['fields' => 'names']);
                $brand = !is_wp_error($brands) ? ($brands[0] ?? strtoupper($post_type)) : strtoupper($post_type);
                $image = function_exists('pc_public_tech_image_url') ? (string) pc_public_tech_image_url((int) $post->ID) : '';
            ?>
                <li>
                    <a href="<?php echo esc_url(get_permalink($post)); ?>">
                        <b><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></b>
                        <?php if ($image) : ?>
                            <img loading="lazy" decoding="async" width="44" height="54" src="<?php echo esc_url($image); ?>" alt="" referrerpolicy="no-referrer">
                        <?php endif; ?>
                        <span>
                            <small><?php echo esc_html($brand); ?></small>
                            <strong><?php echo esc_html($post->post_title); ?></strong>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php
}

function ps_sidebar_ad_slot(): void
{
    ?>
    <div class="sidebar-ad-slot" aria-label="광고">
        <span>광고</span>
        <div data-ad-slot="sidebar-sticky">
            <small>광고</small>
            <p>Google 광고 영역</p>
        </div>
    </div>
    <?php
}

function ps_breadcrumbs(bool $contained = false): void
{
    if (!function_exists('pc_breadcrumb_items')) {
        return;
    }
    $items = pc_breadcrumb_items();
    if (count($items) < 2) {
        return;
    }
    ?>
    <nav class="breadcrumbs<?php echo $contained ? '' : ' shell'; ?>" aria-label="현재 위치">
        <?php foreach ($items as $index => $item) : ?>
            <?php if ($index) : ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($item['url']) : ?>
                <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a>
            <?php else : ?>
                <strong aria-current="page"><?php echo esc_html($item['name']); ?></strong>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}

function ps_spec_label(?string $section, ?string $field, string $id): void
{
    $description = function_exists('pc_spec_glossary') ? pc_spec_glossary($section, $field) : null;
    ?>
    <div class="spec-key">
        <strong><?php echo esc_html(pc_translate_spec_field($field)); ?></strong>
        <?php if ($description) : ?>
            <button type="button" aria-label="<?php echo esc_attr(pc_translate_spec_field($field)); ?> 설명" aria-describedby="<?php echo esc_attr($id); ?>">ⓘ</button>
            <span class="spec-tooltip" id="<?php echo esc_attr($id); ?>" role="tooltip"><?php echo esc_html($description); ?></span>
        <?php endif; ?>
    </div>
    <?php
}

function ps_brand_navigation(): void
{
    $brands = ['Samsung', 'Apple', 'Google', 'Xiaomi', 'Huawei', 'LG'];
    $catalogs = [
        ['phone', '스마트폰'],
        ['laptop', '노트북'],
        ['cpu', 'CPU'],
        ['gpu', 'GPU'],
        ['ssd', 'SSD'],
    ];
    ?>
    <nav class="brand-nav" aria-label="카테고리와 브랜드 바로가기">
        <div class="brand-nav__inner shell">
            <?php foreach ($catalogs as [$type, $label]) : ?>
                <a class="catalog-chip <?php echo is_post_type_archive($type) || is_singular($type) ? 'is-active' : ''; ?>" href="<?php echo esc_url(ps_catalog_archive_url($type)); ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
            <span class="brand-nav__divider" aria-hidden="true"></span>
            <?php foreach ($brands as $brand) : ?>
                <?php
                $term = get_term_by('name', $brand, 'phone_brand');
                $active = is_tax('phone_brand') && $term && get_queried_object_id() === (int) $term->term_id;
                ?>
                <a class="<?php echo $active ? 'is-active' : ''; ?>" href="<?php echo esc_url($term ? get_term_link($term) : get_post_type_archive_link('phone')); ?>"><?php echo esc_html($brand); ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
}
