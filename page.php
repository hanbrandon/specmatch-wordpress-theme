<?php get_header(); ?>
<main class="site-main shell" id="main-content">
    <?php while (have_posts()) : the_post(); ?>
        <article class="policy-page">
            <header class="archive-header">
                <p class="eyebrow">스펙매치 안내</p>
                <h1><?php the_title(); ?></h1>
            </header>
            <div class="policy-page__content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
