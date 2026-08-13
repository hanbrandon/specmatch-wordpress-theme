<?php
get_header();
$search_type = sanitize_key((string) ($_GET['search_type'] ?? 'all'));
if (!in_array($search_type, ['all', 'phone', 'laptop', 'cpu', 'gpu', 'ssd'], true)) $search_type = 'all';
$search_types = ['all' => '전체', 'phone' => '스마트폰', 'laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU', 'ssd' => 'SSD'];
global $wp_query;
?>
<main class="site-main shell" id="main-content">
    <header class="archive-header">
        <p class="eyebrow">검색 결과</p>
        <h1>“<?php echo esc_html(get_search_query()); ?>”</h1>
        <p class="search-result-count">총 <?php echo esc_html(number_format_i18n((int) $wp_query->found_posts)); ?>개 제품</p>
    </header>
    <form class="search-page-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" data-catalog-search>
        <label for="search-page-query">제품 검색</label>
        <div class="search-page-form__controls">
            <input
                id="search-page-query"
                type="search"
                name="s"
                value="<?php echo esc_attr(get_search_query()); ?>"
                placeholder="예: Galaxy S25, MacBook Pro, Ryzen 9, RTX 5090"
                autocomplete="off"
                data-search-input
            >
            <button type="submit">검색</button>
        </div>
        <div class="search-suggestions" data-search-suggestions hidden></div>
        <nav class="search-type-filter" aria-label="제품 카테고리">
            <?php foreach ($search_types as $value => $label) : ?>
                <label class="<?php echo $search_type === $value ? 'is-active' : ''; ?>">
                    <input type="radio" name="search_type" value="<?php echo esc_attr($value); ?>" <?php checked($search_type, $value); ?>>
                    <span><?php echo esc_html($label); ?></span>
                </label>
            <?php endforeach; ?>
        </nav>
    </form>
    <?php if (have_posts()) : ?>
        <div class="phone-grid">
            <?php $rank = ((max(1, get_query_var('paged')) - 1) * (int) $wp_query->get('posts_per_page')) + 1; while (have_posts()) : the_post(); ?>
                <?php if (get_post_type() === 'phone') : ?>
                    <?php ps_phone_card(get_post(), $rank++); ?>
                <?php elseif (in_array(get_post_type(), ['laptop', 'cpu', 'gpu', 'ssd'], true)) : ?>
                    <?php ps_tech_card(get_post(), $rank++); ?>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
        <nav class="pagination"><?php the_posts_pagination([
            'mid_size' => 2,
            'prev_text' => '←',
            'next_text' => '→',
            'add_args' => $search_type !== 'all' ? ['search_type' => $search_type] : [],
        ]); ?></nav>
    <?php else : ?>
        <section class="empty-state">
            <h2>일치하는 제품을 찾지 못했습니다.</h2>
            <p>하이픈이나 띄어쓰기는 자동으로 처리됩니다. 제품명 일부 또는 브랜드명으로 다시 검색해 보세요.</p>
        </section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
