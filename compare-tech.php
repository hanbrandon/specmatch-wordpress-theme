<?php
get_header();
[$product_a, $product_b] = pc_compare_tech_posts();
$type = pc_compare_type();
$type_labels = ['laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU', 'ssd' => 'SSD'];
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
        if ($type === 'ssd') {
            $ssd_card_a = ps_ssd_scorecard((int) $product_a->ID);
            $ssd_card_b = ps_ssd_scorecard((int) $product_b->ID);
        }
        ?>
        <header class="compare-hero">
            <p class="eyebrow"><?php echo esc_html($type_labels[$type] ?? '제품'); ?> / 나란히 비교</p>
            <h1><?php echo esc_html($product_a->post_title); ?><span>vs</span><?php echo esc_html($product_b->post_title); ?></h1>
            <p>두 <?php echo esc_html($type_labels[$type]); ?>의 벤치마크와 전체 사양을 같은 기준으로 비교합니다.</p>
        </header>
        <section class="compare-identities">
            <?php foreach ([$product_a, $product_b] as $product) :
                $image = function_exists('pc_public_tech_image_url') ? (string) pc_public_tech_image_url((int) $product->ID) : '';
                $brands = wp_get_post_terms($product->ID, 'hardware_brand', ['fields' => 'names']);
            ?>
                <article>
                    <?php if ($type === 'ssd') : ?>
                        <?php ps_ssd_vector_mark('ssd-vector--compare'); ?>
                    <?php elseif ($image) : ?><img loading="eager" decoding="async" width="240" height="190" src="<?php echo esc_url($image); ?>" alt="" referrerpolicy="no-referrer"><?php endif; ?>
                    <div>
                        <span><?php echo esc_html(!is_wp_error($brands) ? ($brands[0] ?? strtoupper($type)) : strtoupper($type)); ?></span>
                        <h2><?php echo esc_html($product->post_title); ?></h2>
                        <a href="<?php echo esc_url(get_permalink($product)); ?>">상세 스펙 보기</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <?php if ($type === 'ssd') : ?>
            <?php
            $overall_a = $ssd_card_a['overall'];
            $overall_b = $ssd_card_b['overall'];
            $winner = $overall_a !== null && $overall_b !== null
                ? ($overall_a === $overall_b ? 'tie' : ($overall_a > $overall_b ? 'a' : 'b')) : 'unknown';
            ?>
            <section class="ssd-compare-verdict" aria-labelledby="ssd-compare-verdict-title">
                <div><span>SPEC MATCH SCORE</span><h2 id="ssd-compare-verdict-title">스펙 기준 비교 결과</h2><p>가격을 제외하고 성능·내구성·기능·효율을 같은 규칙으로 평가했습니다.</p></div>
                <div class="ssd-compare-overall">
                    <article class="<?php echo $winner === 'a' ? 'is-winner' : ''; ?>"><span><?php echo esc_html($product_a->post_title); ?></span><strong><?php echo $overall_a !== null ? esc_html((string) $overall_a) : '—'; ?><small>/100</small></strong><?php if ($winner === 'a') : ?><b>종합 우세</b><?php endif; ?></article>
                    <i>VS</i>
                    <article class="<?php echo $winner === 'b' ? 'is-winner' : ''; ?>"><span><?php echo esc_html($product_b->post_title); ?></span><strong><?php echo $overall_b !== null ? esc_html((string) $overall_b) : '—'; ?><small>/100</small></strong><?php if ($winner === 'b') : ?><b>종합 우세</b><?php endif; ?></article>
                </div>
                <div class="ssd-compare-category-grid">
                    <?php foreach ($ssd_card_a['categories'] as $key => $category_a) : ?>
                        <?php $category_b = $ssd_card_b['categories'][$key]; $a_score = $category_a['score']; $b_score = $category_b['score']; ?>
                        <div>
                            <strong><?php echo esc_html($category_a['label']); ?></strong>
                            <span class="<?php echo $a_score !== null && $b_score !== null && $a_score > $b_score ? 'is-better' : ''; ?>"><?php echo $a_score !== null ? esc_html((string) $a_score) : '—'; ?><?php if ($a_score !== null && $b_score !== null && $a_score > $b_score) : ?><b>우세</b><?php endif; ?></span>
                            <span class="<?php echo $a_score !== null && $b_score !== null && $b_score > $a_score ? 'is-better' : ''; ?>"><?php echo $b_score !== null ? esc_html((string) $b_score) : '—'; ?><?php if ($a_score !== null && $b_score !== null && $b_score > $a_score) : ?><b>우세</b><?php endif; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="score-note">누락된 항목은 계산에서 제외합니다. 점수 차이가 작다면 실제 체감 차이도 제한적일 수 있습니다.</p>
            </section>
        <?php endif; ?>
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
                    <?php
                    $higher_is_better = $type === 'ssd' && in_array($row['field'], ['Sequential Read', 'Sequential Write', 'Random Read', 'Random Write', 'Endurance', 'Warranty', 'MTBF'], true);
                    $a_number = $higher_is_better && preg_match('/[\d,.]+/', (string) $row['a'], $a_match) ? (float) str_replace(',', '', $a_match[0]) : null;
                    $b_number = $higher_is_better && preg_match('/[\d,.]+/', (string) $row['b'], $b_match) ? (float) str_replace(',', '', $b_match[0]) : null;
                    ?>
                    <span class="<?php echo $a_number !== null && $b_number !== null && $a_number > $b_number ? 'is-spec-winner' : ''; ?>"><?php echo esc_html($type === 'ssd' ? pc_translate_tech_value($row['a'] ?: '—') : ($row['a'] ?: '—')); ?><?php if ($a_number !== null && $b_number !== null && $a_number > $b_number) : ?><b>우세</b><?php endif; ?></span>
                    <span class="<?php echo $a_number !== null && $b_number !== null && $b_number > $a_number ? 'is-spec-winner' : ''; ?>"><?php echo esc_html($type === 'ssd' ? pc_translate_tech_value($row['b'] ?: '—') : ($row['b'] ?: '—')); ?><?php if ($a_number !== null && $b_number !== null && $b_number > $a_number) : ?><b>우세</b><?php endif; ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php get_footer(); ?>
