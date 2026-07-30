<?php get_header(); ?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <header class="archive-header">
        <p class="eyebrow">브랜드별 스마트폰</p>
        <h1><?php single_term_title(); ?></h1>
    </header>
    <?php ps_category_brand_filter('phone'); ?>
    <?php ps_catalog_tools('phone'); ?>
    <div class="brand-catalog-layout">
        <div class="brand-catalog-main">
            <div class="phone-grid" data-catalog-view>
                <?php $rank = 1; while (have_posts()) : the_post(); ?>
                    <?php ps_phone_card(get_post(), $rank++); ?>
                <?php endwhile; ?>
            </div>
            <nav class="pagination"><?php the_posts_pagination(['mid_size' => 2, 'prev_text' => '←', 'next_text' => '→']); ?></nav>
        </div>
        <aside class="brand-side-widgets" aria-label="인기 및 최신 휴대폰">
            <?php ps_phone_sidebar_widget('많이 찾는 폰', 'popular', pc_sidebar_phone_posts('popular', 5)); ?>
            <?php ps_phone_sidebar_widget('최신 폰', 'newest', pc_sidebar_phone_posts('newest', 5)); ?>
            <?php ps_sidebar_ad_slot(); ?>
        </aside>
    </div>
</main>
<?php get_footer(); ?>
