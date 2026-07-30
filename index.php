<?php get_header(); ?>
<main class="site-main shell" id="main-content">
    <header class="archive-header"><p class="eyebrow">제품 목록</p><h1><?php bloginfo('name'); ?></h1></header>
    <?php while (have_posts()) : the_post(); ?>
        <article><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2></article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
