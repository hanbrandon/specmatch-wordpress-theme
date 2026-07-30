<?php
get_header();
$type = (string) get_query_var('post_type');
$config = [
    'laptop' => ['노트북', '휴대성과 성능, 화면과 배터리를 같은 기준으로 비교합니다.', '노트북 데이터베이스'],
    'cpu' => ['CPU', '코어 구성부터 실제 벤치마크와 전력 효율까지 정리합니다.', '프로세서 데이터베이스'],
    'gpu' => ['GPU', '그래픽 성능, 메모리, 소비전력을 데이터로 읽습니다.', '그래픽카드 데이터베이스'],
][$type] ?? ['하드웨어', '성능과 사양을 비교합니다.', '하드웨어 데이터베이스'];
$active_brand = sanitize_text_field((string) get_query_var('tech_brand'));
$archive_title = $active_brand ? ucwords(str_replace('-', ' ', $active_brand)) . ' ' . $config[0] : $config[0];
if ($active_brand) {
    $config[1] = $archive_title . '의 최신 제품, 평가 점수와 전체 사양을 출시일순으로 정리합니다.';
}
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <header class="catalog-archive-hero">
        <p><?php echo esc_html($config[2]); ?></p>
        <h1><?php echo esc_html($archive_title); ?></h1>
        <div>
            <strong><?php echo esc_html(number_format_i18n((int) $wp_query->found_posts)); ?></strong>
            <span><?php echo esc_html($config[1]); ?></span>
        </div>
    </header>
    <?php ps_category_brand_filter($type); ?>
    <?php ps_catalog_tools($type); ?>
    <div class="archive-catalog-layout">
        <div class="archive-catalog-main">
            <?php if (have_posts()) : ?>
                <section class="tech-grid" data-catalog-view>
                    <?php
                    $rank = ((max(1, (int) get_query_var('paged')) - 1) * (int) get_query_var('posts_per_page')) + 1;
                    while (have_posts()) :
                        the_post();
                        ps_tech_card(get_post(), $rank++);
                    endwhile;
                    ?>
                </section>
                <nav class="pagination"><?php the_posts_pagination(['mid_size' => 2, 'prev_text' => '←', 'next_text' => '→']); ?></nav>
            <?php else : ?>
                <section class="catalog-loading">
                    <span>데이터 수집 진행 중</span>
                    <h2>데이터를 정리하고 있습니다.</h2>
                    <p>수집이 끝나는 대로 벤치마크, 핵심 사양, 비교 기능을 순차적으로 공개합니다.</p>
                    <div><i></i><i></i><i></i><i></i></div>
                </section>
            <?php endif; ?>
        </div>
        <aside class="archive-side-widgets" aria-label="<?php echo esc_attr($config[0]); ?> 인기 및 최신 제품">
            <?php
            $widget_brand_name = $active_brand ? ucwords(str_replace('-', ' ', $active_brand)) : '';
            ps_tech_sidebar_widget(
                ($widget_brand_name ? $widget_brand_name . ' ' : '') . '높은 평가 제품',
                'popular',
                $type,
                ps_tech_sidebar_posts($type, 'popular', 5, $active_brand)
            );
            ps_tech_sidebar_widget(
                ($widget_brand_name ? $widget_brand_name . ' ' : '최신 ') . ($widget_brand_name ? '최신 ' . $config[0] : $config[0]),
                'newest',
                $type,
                ps_tech_sidebar_posts($type, 'newest', 5, $active_brand)
            );
            ?>
            <?php ps_sidebar_ad_slot(); ?>
        </aside>
    </div>
</main>
<?php get_footer(); ?>
