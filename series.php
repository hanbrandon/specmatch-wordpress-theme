<?php
get_header();
$type = sanitize_key((string) get_query_var('pc_series_type'));
$slug = sanitize_title((string) get_query_var('pc_series_slug'));
$posts = pc_series_posts();
$label = $posts ? (string) get_post_meta($posts[0]->ID, '_catalog_series_label', true) : ucwords(str_replace('-', ' ', $slug));
$type_labels = ['phone' => '스마트폰', 'laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
if (!$posts) {
    status_header(404);
}
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <header class="catalog-archive-hero series-hero">
        <p>제품 시리즈 / 세대별 목록</p>
        <h1><?php echo esc_html($label); ?></h1>
        <div>
            <strong><?php echo esc_html(number_format_i18n(count($posts))); ?></strong>
            <span><?php echo esc_html($label); ?>에 속한 <?php echo esc_html($type_labels[$type] ?? '제품'); ?>을 출시일과 주요 사양 기준으로 정리합니다.</span>
        </div>
    </header>
    <?php if ($posts) : ?>
        <?php if ($type === 'phone') : ?>
            <div class="phone-grid series-product-grid">
                <?php foreach ($posts as $index => $post) ps_phone_card($post, $index + 1); ?>
            </div>
        <?php else : ?>
            <section class="tech-index-list series-product-list">
                <header class="tech-index-list__head"><span>번호</span><span>브랜드</span><span>제품명</span><span>출시일</span><span>평가 점수</span></header>
                <?php foreach ($posts as $index => $post) ps_tech_compact_row($post, $index + 1); ?>
            </section>
        <?php endif; ?>
    <?php else : ?>
        <section class="empty-state"><h1>시리즈 제품을 찾지 못했습니다.</h1></section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
