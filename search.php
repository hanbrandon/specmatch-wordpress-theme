<?php get_header(); ?>
<main class="site-main shell" id="main-content">
    <header class="archive-header">
        <p class="eyebrow">검색 결과</p>
        <h1>“<?php echo esc_html(get_search_query()); ?>”</h1>
    </header>
    <form class="search-page-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <label for="search-page-query">제품 검색</label>
        <div class="search-page-form__controls">
            <input
                id="search-page-query"
                type="search"
                name="s"
                value="<?php echo esc_attr(get_search_query()); ?>"
                placeholder="예: Galaxy S25, MacBook Pro, Ryzen 9, RTX 5090"
                autocomplete="off"
            >
            <button type="submit">검색</button>
        </div>
    </form>
    <?php if (have_posts()) : ?>
        <div class="phone-grid">
            <?php $rank = 1; while (have_posts()) : the_post(); ?>
                <?php if (get_post_type() === 'phone') : ?>
                    <?php ps_phone_card(get_post(), $rank++); ?>
                <?php elseif (in_array(get_post_type(), ['laptop', 'cpu', 'gpu'], true)) : ?>
                    <?php ps_tech_card(get_post(), $rank++); ?>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <section class="empty-state"><h1>일치하는 기기를 찾지 못했습니다.</h1></section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
