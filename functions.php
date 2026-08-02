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
    wp_enqueue_style(
        'phone-seoul-fonts',
        'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans+KR:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap',
        [],
        null
    );
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
    if ($relation_type === 'preconnect') {
        $urls[] = ['href' => 'https://fonts.googleapis.com', 'crossorigin' => 'anonymous'];
        $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'];
    }
    return $urls;
}
add_filter('wp_resource_hints', 'ps_resource_hints', 10, 2);

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
        $query->set('post_type', ['phone', 'laptop', 'cpu', 'gpu']);
        $query->set('meta_key', '_catalog_release_date');
        $query->set('orderby', ['meta_value' => 'DESC', 'title' => 'ASC']);
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'ps_catalog_search_scope', 30);

function ps_dense_hardware_archives(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive(['laptop', 'cpu', 'gpu'])) {
        return;
    }
    $query->set('posts_per_page', 80);
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
add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'tech_brand';
    $vars[] = 'catalog_sort';
    $vars[] = 'catalog_q';
    $vars[] = 'catalog_year';
    $vars[] = 'min_score';
    return $vars;
});

function ps_apply_catalog_filters(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    $is_phone = $query->is_post_type_archive('phone') || $query->is_tax('phone_brand');
    $is_hardware = $query->is_post_type_archive(['laptop', 'cpu', 'gpu']);
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
    $keyword = trim((string) $query->get('s'));
    if ($keyword === '') {
        return $search;
    }
    global $wpdb;
    $like = '%' . $wpdb->esc_like($keyword) . '%';
    return $wpdb->prepare(
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
add_filter('posts_search', 'ps_expand_global_catalog_search', 30, 2);

function ps_apply_catalog_sorting(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    $sort = sanitize_key((string) get_query_var('catalog_sort'));
    $post_type = (string) $query->get('post_type');
    $is_phone = $query->is_post_type_archive('phone') || $query->is_tax('phone_brand');
    $is_hardware = $query->is_post_type_archive(['laptop', 'cpu', 'gpu']);
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
            <span><?php echo esc_html($active ?: '전체 브랜드'); ?></span>
            <small>변경</small>
            <i aria-hidden="true"></i>
        </button>
        <div class="category-brand-filter__options" data-brand-filter-options>
            <a class="<?php echo $active === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url(ps_catalog_archive_url($post_type)); ?>"<?php echo $active === '' ? ' aria-current="page"' : ''; ?>>전체</a>
            <?php foreach ($brands as $brand) : ?>
                <?php $selected = strcasecmp($active, $brand['name']) === 0; ?>
                <a class="<?php echo $selected ? 'is-active' : ''; ?>" href="<?php echo esc_url($brand['url']); ?>"<?php echo $selected ? ' aria-current="page"' : ''; ?>>
                    <?php echo esc_html($brand['name']); ?><small><?php echo esc_html(number_format_i18n($brand['count'])); ?></small>
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
            <label>
                <span>최소 평가</span>
                <div class="catalog-filter__select">
                <select name="min_score">
                    <option value="">전체 점수</option>
                    <?php foreach ([50, 70, 85] as $score) : ?>
                        <option value="<?php echo esc_attr($score); ?>" <?php selected((int) get_query_var('min_score'), $score); ?>><?php echo esc_html($score); ?>점 이상</option>
                    <?php endforeach; ?>
                </select>
                </div>
            </label>
        <?php endif; ?>
        <button type="submit">필터 적용</button>
        <?php if (get_query_var('catalog_q') || get_query_var('catalog_year') || get_query_var('min_score')) : ?>
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
    $display_date = ps_catalog_display_date((int) $post->ID, (string) ($device?->announced ?? ''));
    ?>
    <article class="phone-card">
        <span class="rank"><?php echo esc_html(str_pad((string) $rank, 2, '0', STR_PAD_LEFT)); ?></span>
        <?php if (pc_public_image_url($device)) : ?>
            <img loading="lazy" fetchpriority="low" decoding="async" width="300" height="220" src="<?php echo esc_url(pc_public_image_url($device)); ?>" alt="<?php echo esc_attr($post->post_title); ?>">
        <?php endif; ?>
        <p class="phone-card__brand"><?php echo esc_html($device?->brand ?: '스마트폰'); ?></p>
        <h3><?php echo esc_html($post->post_title); ?></h3>
        <p class="phone-card__date"><?php echo esc_html($display_date ?: '날짜 미상'); ?></p>
        <a href="<?php echo esc_url(get_permalink($post)); ?>"<?php echo is_search() ? ' data-track-event="search_click" data-track-post="' . esc_attr((string) $post->ID) . '"' : ''; ?>><?php echo esc_html($post->post_title); ?> 보기</a>
    </article>
    <?php
}

function ps_tech_card(?WP_Post $post = null, int $rank = 0): void
{
    $post = $post ?: get_post();
    $type = get_post_type($post);
    $labels = ['laptop' => '노트북', 'cpu' => '프로세서', 'gpu' => '그래픽카드'];
    $score = get_post_meta($post->ID, '_tech_score', true);
    $display_date = ps_catalog_display_date((int) $post->ID, (string) get_post_meta($post->ID, '_tech_launched', true));
    ?>
    <article class="tech-card">
        <div class="tech-card__meta">
            <span><?php echo esc_html(str_pad((string) $rank, 2, '0', STR_PAD_LEFT)); ?></span>
            <small><?php echo esc_html($labels[$type] ?? strtoupper((string) $type)); ?></small>
        </div>
        <?php $image_url = function_exists('pc_public_tech_image_url') ? pc_public_tech_image_url((int) $post->ID) : null; ?>
        <?php if ($image_url) : ?>
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

function ps_related_tech_posts(int $post_id, string $post_type, int $limit = 4): array
{
    if (!in_array($post_type, ['laptop', 'cpu', 'gpu'], true)) {
        return [];
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
    if (!in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)) {
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
    if (!in_array($post_type, ['phone', 'laptop', 'cpu', 'gpu'], true)) {
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
                            <small><?php echo esc_html($device?->brand ?: '스마트폰'); ?></small>
                            <strong><?php echo esc_html($post->post_title); ?></strong>
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
    if (!in_array($post_type, ['laptop', 'cpu', 'gpu'], true)) {
        return [];
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
        if ($measured) return $measured;
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
    return get_posts($args);
}

function ps_tech_sidebar_widget(string $title, string $mode, string $post_type, array $posts): void
{
    if (!$posts) {
        return;
    }
    ?>
    <section class="phone-side-widget phone-side-widget--<?php echo esc_attr($mode); ?>">
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
