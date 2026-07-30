<?php get_header(); ?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <header class="archive-header">
        <p class="eyebrow">전체 스마트폰 데이터베이스</p>
        <h1>전체 휴대폰</h1>
    </header>
    <?php ps_category_brand_filter('phone'); ?>
    <?php ps_catalog_tools('phone'); ?>
    <div class="archive-catalog-layout">
        <div class="archive-catalog-main">
            <div class="phone-grid">
                <?php $rank = ((max(1, get_query_var('paged')) - 1) * (int) get_option('posts_per_page')) + 1; ?>
                <?php while (have_posts()) : the_post(); ?>
                    <?php ps_phone_card(get_post(), $rank++); ?>
                <?php endwhile; ?>
            </div>
            <nav class="pagination"><?php the_posts_pagination(['mid_size' => 2, 'prev_text' => '←', 'next_text' => '→']); ?></nav>
        </div>
        <aside class="archive-side-widgets" aria-label="인기 및 최신 휴대폰">
            <?php ps_phone_sidebar_widget('많이 찾는 폰', 'popular', pc_sidebar_phone_posts('popular', 5)); ?>
            <?php ps_phone_sidebar_widget('최신 폰', 'newest', pc_sidebar_phone_posts('newest', 5)); ?>
            <?php ps_sidebar_ad_slot(); ?>
        </aside>
    </div>
</main>
<?php get_footer(); ?>
