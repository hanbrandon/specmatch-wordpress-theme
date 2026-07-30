<?php
get_header();
[$product_a, $product_b] = pc_compare_tech_posts();
$type = pc_compare_type();
$type_labels = ['laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU'];
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <?php if (!$product_a || !$product_b) : ?>
        <section class="empty-state">
            <p class="eyebrow">비교할 수 없음</p>
            <h1>비교할 제품을 찾지 못했습니다.</h1>
            <a class="button" href="<?php echo esc_url(home_url('/compare/')); ?>">다시 선택하기</a>
        </section>
    <?php else : ?>
        <?php
        $rows = pc_compare_tech_rows($product_a, $product_b);
        $scores_a = json_decode((string) get_post_meta($product_a->ID, '_tech_scores', true), true) ?: [];
        $scores_b = json_decode((string) get_post_meta($product_b->ID, '_tech_scores', true), true) ?: [];
        ?>
        <header class="compare-hero">
            <p class="eyebrow"><?php echo esc_html($type_labels[$type] ?? '제품'); ?> / 나란히 비교</p>
            <h1><?php echo esc_html($product_a->post_title); ?><span>vs</span><?php echo esc_html($product_b->post_title); ?></h1>
            <p>두 <?php echo esc_html($type_labels[$type]); ?>의 벤치마크와 전체 사양을 같은 기준으로 비교합니다.</p>
        </header>
        <section class="compare-identities">
            <?php foreach ([$product_a, $product_b] as $product) :
                $image = (string) get_post_meta($product->ID, '_tech_image_url', true);
                $brands = wp_get_post_terms($product->ID, 'hardware_brand', ['fields' => 'names']);
            ?>
                <article>
                    <?php if ($image) : ?><img loading="eager" decoding="async" width="240" height="190" src="<?php echo esc_url($image); ?>" alt="" referrerpolicy="no-referrer"><?php endif; ?>
                    <div>
                        <span><?php echo esc_html(!is_wp_error($brands) ? ($brands[0] ?? strtoupper($type)) : strtoupper($type)); ?></span>
                        <h2><?php echo esc_html($product->post_title); ?></h2>
                        <a href="<?php echo esc_url(get_permalink($product)); ?>">상세 스펙 보기</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <?php if ($scores_a || $scores_b) : ?>
            <section class="tech-compare-scores">
                <div class="section-heading"><div><p class="eyebrow">성능 측정</p><h2>평가 및 벤치마크</h2></div></div>
                <?php foreach (array_unique(array_merge(array_keys($scores_a), array_keys($scores_b))) as $score_name) : ?>
                    <div>
                        <strong><?php echo esc_html(pc_translate_tech_key($score_name)); ?></strong>
                        <span><?php echo esc_html($scores_a[$score_name] ?? '—'); ?></span>
                        <span><?php echo esc_html($scores_b[$score_name] ?? '—'); ?></span>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <div class="compare-filters" data-compare-filters>
            <span>비교표 보기</span>
            <button class="is-active" type="button" data-mode="different">주요 차이</button>
            <button type="button" data-mode="all">전체 스펙</button>
        </div>
        <section class="spec-sheet compare-sheet" data-compare-table>
            <?php $last_section = ''; ?>
            <?php foreach ($rows as $row) : ?>
                <?php if ($last_section !== $row['section']) : $last_section = $row['section']; ?>
                    <h2><?php echo esc_html(pc_translate_tech_section($row['section'])); ?></h2>
                <?php endif; ?>
                <div class="compare-row <?php echo $row['same'] ? 'is-same' : ''; ?>" <?php echo $row['same'] ? 'hidden' : ''; ?>>
                    <div class="spec-key"><strong><?php echo esc_html(pc_translate_tech_key($row['field'])); ?></strong></div>
                    <span><?php echo esc_html($row['a'] ?: '—'); ?></span>
                    <span><?php echo esc_html($row['b'] ?: '—'); ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
