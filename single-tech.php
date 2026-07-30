<?php
get_header();
$type = get_post_type();
$label = ['laptop' => '노트북', 'cpu' => '프로세서', 'gpu' => '그래픽카드'][$type] ?? '하드웨어';
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <?php while (have_posts()) : the_post(); ?>
        <article class="tech-detail">
            <header>
                <p><?php echo esc_html($label); ?> / 제품 정보</p>
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?><div class="tech-detail__lede"><?php the_excerpt(); ?></div><?php endif; ?>
                <?php ps_product_series_link((int) get_the_ID()); ?>
            </header>
            <div class="tech-detail__body">
                <?php
                $image = get_post_meta(get_the_ID(), '_tech_image_url', true);
                $scores = json_decode((string) get_post_meta(get_the_ID(), '_tech_scores', true), true) ?: [];
                $configurations = json_decode((string) get_post_meta(get_the_ID(), '_tech_configurations', true), true) ?: [];
                $specs = json_decode((string) get_post_meta(get_the_ID(), '_tech_specs', true), true) ?: [];
                $section_notes = [
                    'Games' => '각 숫자는 순서대로 1080p 높음, 1080p 울트라, 1440p 울트라, 4K 울트라의 평균 FPS입니다. FPS가 높을수록 화면 움직임이 더 부드럽습니다.',
                    '3D Mark' => '그래픽 성능을 수치화한 벤치마크 원점수입니다. 높을수록 좋지만, 서로 다른 시험끼리가 아니라 같은 시험의 다른 GPU와 비교하세요.',
                    'GeekBench 6 OpenCL' => 'GPU 연산 작업별 처리 속도입니다. img/sec·Gpixels/sec·FPS처럼 단위가 다르므로 서로 다른 행의 숫자를 직접 비교하면 안 됩니다.',
                    'PassMark Graphics' => '2D, DirectX, 연산 성능을 각각 측정합니다. 단위가 다른 항목은 동일 항목의 다른 GPU와 비교하세요.',
                    'GeekBench 6 ML' => '머신러닝 작업별 처리 성능 원점수입니다. SP는 단정밀도, HP는 반정밀도, Q는 양자화 연산을 뜻하며 점수가 높을수록 좋습니다.',
                    'Raw Performance' => '이론상 최대 처리량입니다. 실제 게임 FPS가 아니며 GPU 구조와 작업 종류에 따라 체감 성능은 달라질 수 있습니다.',
                    'API' => '성능 점수가 아니라 지원하는 GPU 연산 인터페이스와 실행 환경입니다.',
                ];
                $game_labels = ['1080p 높음', '1080p 울트라', '1440p 울트라', '4K 울트라'];
                $game_summary_fields = ['1080p High', '1080p Ultra', '1440p Ultra', '4K Ultra'];
                ?>
                <?php if ($image) : ?>
                    <figure class="tech-product-image"><img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>" width="520" height="360" loading="eager" decoding="async" referrerpolicy="no-referrer"></figure>
                <?php endif; ?>
                <aside class="tech-data-note" aria-label="데이터 해석 안내">
                    <strong>데이터 읽는 법</strong>
                    <p>평가 점수는 수집된 사양과 벤치마크를 제품군 안에서 비교하기 위한 참고 지표입니다. 실제 성능은 사용 환경, 전력 설정과 소프트웨어 버전에 따라 달라질 수 있습니다.</p>
                    <span>마지막 데이터 갱신: <?php echo esc_html(get_the_modified_date('Y년 n월 j일')); ?></span>
                </aside>
                <?php if ($scores) : ?>
                    <section class="tech-data-section">
                        <header><span>성능</span><h2>평가 및 벤치마크</h2></header>
                        <p class="tech-section-guide">100점 척도 평가는 막대로 표시합니다. 그보다 큰 벤치마크 원점수는 같은 시험의 다른 제품과 비교하세요.</p>
                        <div class="tech-score-grid">
                            <?php foreach ($scores as $score_name => $score) : ?>
                                <?php $numeric_score = is_numeric($score) ? (float) $score : null; ?>
                                <div>
                                    <span><?php echo esc_html(pc_translate_tech_key($score_name)); ?></span>
                                    <strong><?php echo esc_html($score); ?></strong>
                                    <?php if ($numeric_score !== null && $numeric_score >= 0 && $numeric_score <= 100) : ?>
                                        <progress max="100" value="<?php echo esc_attr((string) $numeric_score); ?>" aria-label="<?php echo esc_attr(pc_translate_tech_key($score_name) . ' ' . $numeric_score . '점'); ?>"></progress>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                <?php if ($configurations) : ?>
                    <section class="tech-data-section">
                        <header><span>제품 구성</span><h2>선택 가능한 구성</h2></header>
                        <?php foreach ($configurations as $category => $options) : ?>
                            <div class="tech-config-row">
                                <strong><?php echo esc_html(pc_translate_tech_key($category)); ?></strong>
                                <div>
                                    <?php foreach ((array) $options as $option) : ?>
                                        <span<?php echo !empty($option['is_default']) ? ' class="is-default"' : ''; ?>><?php echo esc_html($option['label'] ?? ''); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
                <?php
                $groups = [];
                foreach ($specs as $spec) {
                    $groups[(string) ($spec['section'] ?? 'Specifications')][] = $spec;
                }
                ?>
                <?php if ($groups) : ?>
                    <section class="tech-data-section">
                        <header><span>상세 정보</span><h2>전체 사양</h2></header>
                        <?php foreach ($groups as $section => $rows) : ?>
                            <?php
                            $is_game_chart = $type === 'gpu' && $section === 'Games';
                            $is_relative_chart = $type === 'gpu' && in_array($section, ['3D Mark', 'GeekBench 6 ML'], true);
                            $note = $type === 'gpu' ? ($section_notes[$section] ?? '') : '';
                            ?>
                            <div class="tech-spec-group<?php echo $is_game_chart || $is_relative_chart ? ' tech-spec-group--visual' : ''; ?>">
                                <div class="tech-spec-group__heading">
                                    <h3><?php echo esc_html(pc_translate_tech_section($section)); ?></h3>
                                    <?php if ($note) : ?><p><?php echo esc_html($note); ?></p><?php endif; ?>
                                </div>
                                <?php if ($is_game_chart) : ?>
                                    <?php
                                    $game_rows = [];
                                    $summary_rows = [];
                                    $margin = '';
                                    $game_max = 0;
                                    foreach ($rows as $row) {
                                        $field = (string) ($row['field'] ?? '');
                                        $value = (string) ($row['value'] ?? '');
                                        if (in_array($field, $game_summary_fields, true)) {
                                            $summary_rows[$field] = (float) $value;
                                            continue;
                                        }
                                        if ($field === 'Margin of Error') {
                                            $margin = $value;
                                            continue;
                                        }
                                        preg_match_all('/\d+(?:\.\d+)?/', $value, $matches);
                                        if (count($matches[0]) === 4) {
                                            $values = array_map('floatval', $matches[0]);
                                            $game_max = max($game_max, ...$values);
                                            $game_rows[] = ['name' => $field, 'values' => $values];
                                        }
                                    }
                                    $game_scale = max(60, (int) ceil($game_max / 50) * 50);
                                    ?>
                                    <div class="gpu-game-chart">
                                        <div class="gpu-game-legend" aria-label="게임 성능 측정 조건">
                                            <?php foreach ($game_labels as $index => $label_text) : ?>
                                                <span class="series-<?php echo esc_attr((string) ($index + 1)); ?>"><?php echo esc_html($label_text); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php foreach ($game_rows as $game) : ?>
                                            <div class="gpu-game-row">
                                                <strong><?php echo esc_html($game['name']); ?></strong>
                                                <div class="gpu-game-bars">
                                                    <?php foreach ($game['values'] as $index => $fps) : ?>
                                                        <div class="gpu-game-bar series-<?php echo esc_attr((string) ($index + 1)); ?>">
                                                            <span><?php echo esc_html($game_labels[$index]); ?></span>
                                                            <progress max="<?php echo esc_attr((string) $game_scale); ?>" value="<?php echo esc_attr((string) $fps); ?>" aria-label="<?php echo esc_attr($game['name'] . ' ' . $game_labels[$index] . ' ' . $fps . ' FPS'); ?>"></progress>
                                                            <b><?php echo esc_html((string) $fps); ?> <small>FPS</small></b>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($summary_rows) : ?>
                                            <div class="gpu-resolution-summary">
                                                <?php foreach ($game_summary_fields as $index => $field) : ?>
                                                    <?php if (!isset($summary_rows[$field])) continue; ?>
                                                    <div class="series-<?php echo esc_attr((string) ($index + 1)); ?>">
                                                        <span><?php echo esc_html($game_labels[$index]); ?> 평균</span>
                                                        <strong><?php echo esc_html((string) $summary_rows[$field]); ?> <small>FPS</small></strong>
                                                        <progress max="<?php echo esc_attr((string) $game_scale); ?>" value="<?php echo esc_attr((string) $summary_rows[$field]); ?>"></progress>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($margin) : ?><p class="gpu-chart-footnote">측정 오차 범위: <?php echo esc_html($margin); ?></p><?php endif; ?>
                                    </div>
                                <?php elseif ($is_relative_chart) : ?>
                                    <?php
                                    $chart_rows = [];
                                    $chart_max = 0;
                                    foreach ($rows as $row) {
                                        $value = trim((string) ($row['value'] ?? ''));
                                        if (preg_match('/^([\d,.]+)/', $value, $match)) {
                                            $number = (float) str_replace(',', '', $match[1]);
                                            $chart_max = max($chart_max, $number);
                                            $chart_rows[] = ['field' => (string) ($row['field'] ?? ''), 'value' => $value, 'number' => $number];
                                        }
                                    }
                                    ?>
                                    <div class="gpu-relative-chart">
                                        <?php foreach ($chart_rows as $chart_row) : ?>
                                            <div>
                                                <span><?php echo esc_html(pc_translate_tech_key($chart_row['field'])); ?></span>
                                                <progress max="<?php echo esc_attr((string) $chart_max); ?>" value="<?php echo esc_attr((string) $chart_row['number']); ?>"></progress>
                                                <strong><?php echo esc_html($chart_row['value']); ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <dl>
                                        <?php foreach ($rows as $row) : ?>
                                            <div>
                                                <dt><?php echo esc_html(pc_translate_tech_key($row['field'] ?? '')); ?></dt>
                                                <dd><?php echo esc_html($row['value'] ?? ''); ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php elseif (get_the_content()) : ?>
                    <?php the_content(); ?>
                <?php endif; ?>
            </div>
            <?php ps_product_connections((int) get_the_ID()); ?>
            <?php $related_products = ps_related_tech_posts((int) get_the_ID(), $type, 4); ?>
            <?php if ($related_products) : ?>
                <section class="tech-related">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">관련 제품 더보기</p>
                            <h2>함께 살펴볼 연관 제품</h2>
                        </div>
                        <a href="<?php echo esc_url(ps_catalog_archive_url($type)); ?>">전체 <?php echo esc_html($label); ?> 보기</a>
                    </div>
                    <div class="tech-grid">
                        <?php foreach ($related_products as $index => $related_product) : ?>
                            <?php ps_tech_card($related_product, $index + 1); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php ps_recent_products_section((int) get_the_ID()); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
