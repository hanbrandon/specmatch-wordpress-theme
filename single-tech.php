<?php
get_header();
$type = get_post_type();
$label = ['laptop' => '노트북', 'cpu' => '프로세서', 'gpu' => '그래픽카드', 'ssd' => 'SSD'][$type] ?? '하드웨어';
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php
        $decode_tech_meta = static function (string $key): array {
            $raw = get_post_meta(get_the_ID(), $key, true);
            if (is_array($raw)) {
                return $raw;
            }
            $decoded = json_decode((string) $raw, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            return is_array($decoded) ? $decoded : [];
        };
        $image = function_exists('pc_public_tech_image_url') ? pc_public_tech_image_url(get_the_ID()) : null;
        $scores = $decode_tech_meta('_tech_scores');
        $configurations = $decode_tech_meta('_tech_configurations');
        $specs = $decode_tech_meta('_tech_specs');
        if ($type === 'ssd' && !$specs && preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/si', (string) get_the_content(), $ssd_content_rows, PREG_SET_ORDER)) {
            foreach ($ssd_content_rows as $row_order => $ssd_content_row) {
                $specs[] = [
                    'section' => 'Specifications',
                    'field' => trim(wp_strip_all_tags(html_entity_decode($ssd_content_row[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                    'value' => trim(wp_strip_all_tags(html_entity_decode($ssd_content_row[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                    'row_order' => $row_order,
                ];
            }
        }
        $groups = [];
        $ssd_summary = [];

        if ($type === 'ssd') {
            $ssd_section = 'SSD Overview';
            $ssd_type_count = 0;
            foreach ($specs as $spec) {
                $field = (string) ($spec['field'] ?? '');
                if ($field === 'Form Factor') $ssd_section = 'SSD Physical';
                elseif ($field === 'Manufacturer' && $ssd_section === 'SSD Physical') $ssd_section = 'SSD Controller';
                elseif ($field === 'Manufacturer' && $ssd_section === 'SSD Controller') $ssd_section = 'SSD NAND';
                elseif ($field === 'Type' && $ssd_section === 'SSD NAND' && ++$ssd_type_count > 1) $ssd_section = 'SSD DRAM';
                elseif ($field === 'Sequential Read') $ssd_section = 'SSD Performance';
                elseif ($field === 'TRIM') $ssd_section = 'SSD Features';
                $groups[$ssd_section][] = $spec;
                if (!isset($ssd_summary[$field])) $ssd_summary[$field] = (string) ($spec['value'] ?? '');
            }
            $ssd_insights = ps_ssd_insights($specs);
            $ssd_scorecard = ps_ssd_scorecard((int) get_the_ID());
            $ssd_faqs = ps_ssd_faqs((int) get_the_ID(), $ssd_scorecard);
            $ssd_status = ps_ssd_product_status((int) get_the_ID());
            $ssd_peer_analysis = ps_ssd_peer_analysis((int) get_the_ID());
            $ssd_fit = ps_ssd_compatibility_and_fit((int) get_the_ID());
        } else {
            foreach ($specs as $spec) {
                $groups[(string) ($spec['section'] ?? 'Specifications')][] = $spec;
            }
        }
        ?>
        <article class="tech-detail tech-detail--<?php echo esc_attr($type); ?>">
            <header>
                <p><?php echo esc_html($label); ?> / 제품 정보</p>
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?><div class="tech-detail__lede"><?php the_excerpt(); ?></div><?php endif; ?>
                <?php ps_product_series_link((int) get_the_ID()); ?>
                <?php if ($type === 'ssd') : ?>
                    <span class="ssd-status ssd-status--<?php echo esc_attr($ssd_status['key']); ?>"><?php echo esc_html($ssd_status['label']); ?></span>
                    <a class="button detail-compare-button" href="<?php echo esc_url(add_query_arg([
                        'type' => 'ssd',
                        'phone' => get_post_field('post_name', get_the_ID()),
                        'name' => get_the_title(),
                        'post_id' => get_the_ID(),
                    ], home_url('/compare/'))); ?>">이 SSD와 비교하기 →</a>
                <?php endif; ?>
            </header>
            <div class="tech-detail__body">
                <?php
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
                <?php if ($type === 'ssd') : ?>
                    <section class="ssd-overview" aria-label="SSD 핵심 사양">
                        <div class="ssd-overview__visual">
                            <span>SSD / QUICK VIEW</span>
                            <?php ps_ssd_vector_mark('ssd-vector--hero'); ?>
                        </div>
                        <div class="ssd-overview__content">
                            <p>핵심 사양</p>
                            <h2>구매 전에 먼저 볼 정보</h2>
                            <dl>
                                <?php foreach (['Capacity' => '용량', 'Form Factor' => '폼팩터', 'Interface' => '인터페이스', 'Sequential Read' => '순차 읽기', 'Sequential Write' => '순차 쓰기', 'Endurance' => '쓰기 내구성'] as $summary_key => $summary_label) : ?>
                                    <?php if (!empty($ssd_summary[$summary_key])) : ?><div><dt><?php echo esc_html($summary_label); ?></dt><dd><?php echo esc_html(pc_translate_tech_value($ssd_summary[$summary_key])); ?></dd></div><?php endif; ?>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    </section>
                <?php elseif ($image) : ?>
                    <figure class="tech-product-image"><img src="<?php echo esc_url($image); ?>" alt="<?php the_title_attribute(); ?>" width="520" height="360" loading="eager" decoding="async" referrerpolicy="no-referrer"></figure>
                <?php endif; ?>
                <aside class="tech-data-note" aria-label="데이터 해석 안내">
                    <strong>데이터 읽는 법</strong>
                    <p><?php echo $type === 'ssd' ? '표기 속도와 내구성은 제조사 사양을 기준으로 하며, 실제 성능은 용량·시스템 구성·작업 유형에 따라 달라질 수 있습니다.' : '평가 점수는 수집된 사양과 벤치마크를 제품군 안에서 비교하기 위한 참고 지표입니다. 실제 성능은 사용 환경, 전력 설정과 소프트웨어 버전에 따라 달라질 수 있습니다.'; ?></p>
                    <span>마지막 데이터 갱신: <?php echo esc_html(get_the_modified_date('Y년 n월 j일')); ?></span>
                </aside>
                <?php if ($type === 'ssd') : ?>
                    <section class="ssd-answer" aria-labelledby="ssd-answer-title">
                        <div>
                            <span>SPEC MATCH VERDICT</span>
                            <h2 id="ssd-answer-title"><?php the_title(); ?> 핵심 결론</h2>
                            <p><?php echo esc_html(sprintf(
                                '%s 인터페이스 기반 제품이며, 공개된 사양을 기준으로 한 자체 평가는 %s입니다. 가격은 평가에 포함하지 않았고 실제 성능은 시스템 구성에 따라 달라질 수 있습니다.',
                                $ssd_scorecard['interface'] ?: '인터페이스 정보 미상',
                                $ssd_scorecard['overall'] !== null ? $ssd_scorecard['overall'] . '점' : '데이터 부족으로 산정 보류'
                            )); ?></p>
                        </div>
                        <div class="ssd-score-dial">
                            <strong><?php echo $ssd_scorecard['overall'] !== null ? esc_html((string) $ssd_scorecard['overall']) : '—'; ?></strong><small>/100</small>
                            <span>평가 기준 v<?php echo esc_html($ssd_scorecard['version']); ?></span>
                        </div>
                    </section>
                    <section class="ssd-scorecard" aria-label="SSD 자체 평가 점수">
                        <?php foreach ($ssd_scorecard['categories'] as $category) : ?>
                            <div>
                                <span><?php echo esc_html($category['label']); ?></span>
                                <strong><?php echo $category['score'] !== null ? esc_html((string) $category['score']) : '—'; ?></strong>
                                <?php if ($category['score'] !== null) : ?><progress max="100" value="<?php echo esc_attr((string) $category['score']); ?>"></progress><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                    <?php if ($ssd_peer_analysis['metrics']) : ?>
                        <section class="ssd-peer-analysis" aria-labelledby="ssd-peer-title">
                            <header><span>자체 데이터 분석</span><h2 id="ssd-peer-title">동급 제품에서의 위치</h2><p><?php echo esc_html($ssd_peer_analysis['position']); ?> 비교 표본은 <?php echo esc_html(number_format_i18n($ssd_peer_analysis['count'])); ?>개입니다.</p></header>
                            <div class="ssd-peer-grid">
                                <?php foreach ($ssd_peer_analysis['metrics'] as $metric) : ?>
                                    <article><span><?php echo esc_html($metric['label']); ?></span><strong>상위 <?php echo esc_html((string) max(1, 101 - $metric['percentile'])); ?>%</strong><p>동급 중앙값 <?php echo esc_html(number_format_i18n($metric['median'], 1)); ?><?php echo $metric['label'] === '평균 소비전력' ? ' W' : ($metric['label'] === '용량당 내구성' ? ' TBW/TB' : ''); ?></p><progress max="100" value="<?php echo esc_attr((string) $metric['percentile']); ?>"></progress></article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                    <section class="ssd-fit-guide" aria-labelledby="ssd-fit-title">
                        <header><span>실사용 판단</span><h2 id="ssd-fit-title">호환성과 추천 대상</h2></header>
                        <div><article><strong>장착 전 확인</strong><ul><?php foreach ($ssd_fit['compatibility'] as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ul></article><article><strong>추천 사용자</strong><ul><?php foreach ($ssd_fit['recommended'] as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ul></article><article><strong>다른 제품이 나을 수 있는 경우</strong><ul><?php foreach ($ssd_fit['not_recommended'] as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ul></article></div>
                    </section>
                    <section class="ssd-analysis" aria-labelledby="ssd-analysis-title">
                        <header><span>구매 판단 가이드</span><h2 id="ssd-analysis-title">이 SSD를 어떻게 봐야 할까요?</h2></header>
                        <div class="ssd-analysis__grid">
                            <section><strong>장점</strong><ul><?php foreach ($ssd_insights['advantages'] as $text) : ?><li><?php echo esc_html($text); ?></li><?php endforeach; ?></ul></section>
                            <section><strong>확인할 점</strong><ul><?php foreach ($ssd_insights['cautions'] as $text) : ?><li><?php echo esc_html($text); ?></li><?php endforeach; ?></ul></section>
                            <section><strong>추천 용도</strong><ul><?php foreach ($ssd_insights['uses'] as $text) : ?><li><?php echo esc_html($text); ?></li><?php endforeach; ?></ul></section>
                        </div>
                        <div class="ssd-analysis__terms">
                            <p><strong>인터페이스</strong><span><?php echo esc_html($ssd_insights['interface']); ?> — SSD와 시스템이 데이터를 주고받는 연결 규격입니다.</span></p>
                            <p><strong>NAND 정보</strong><span><?php echo esc_html($ssd_insights['nand']); ?> — 데이터를 실제로 저장하는 플래시 메모리 구성입니다.</span></p>
                        </div>
                        <p class="ssd-analysis__method">표시된 판단은 공개된 제품 사양을 규칙에 따라 정리한 참고 정보입니다. <a href="<?php echo esc_url(home_url('/methodology/')); ?>">데이터·평가 방법 보기</a></p>
                    </section>
                    <section class="ssd-faq" aria-labelledby="ssd-faq-title">
                        <header><span>자주 묻는 질문</span><h2 id="ssd-faq-title">이 제품에 대해 궁금한 점</h2></header>
                        <?php foreach ($ssd_faqs as $faq) : ?>
                            <details><summary><?php echo esc_html($faq['question']); ?></summary><p><?php echo esc_html($faq['answer']); ?></p></details>
                        <?php endforeach; ?>
                    </section>
                    <aside class="ssd-source-note">
                        <strong>데이터 신뢰 정보</strong>
                        <p>공개된 제품 사양을 한국어로 정리했으며 자체 점수는 가격을 제외한 규칙 기반 참고 지표입니다. 잘못된 정보는 <a href="mailto:<?php echo esc_attr(antispambot((string) get_option('admin_email'))); ?>?subject=<?php echo rawurlencode('[스펙 오류 신고] ' . get_the_title()); ?>">오류 신고</a>로 알려주세요.</p>
                        <span>최종 확인: <?php echo esc_html(get_the_modified_date('Y년 n월 j일')); ?></span>
                    </aside>
                <?php endif; ?>
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
                                                <dd><?php echo esc_html($type === 'ssd' && function_exists('pc_translate_tech_value') ? pc_translate_tech_value($row['value'] ?? '') : ($row['value'] ?? '')); ?></dd>
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
                    <?php if ($type === 'ssd') : ?>
                        <nav class="ssd-related-comparisons" aria-label="추천 SSD 비교">
                            <strong>추천 비교</strong>
                            <?php foreach ($related_products as $related_product) : ?>
                                <a href="<?php echo esc_url(pc_compare_tech_url(get_post(), $related_product)); ?>"><strong><?php the_title(); ?> vs <?php echo esc_html($related_product->post_title); ?></strong><span><?php echo esc_html(ps_ssd_alternative_reason((int) get_the_ID(), (int) $related_product->ID)); ?></span></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <?php ps_recent_products_section((int) get_the_ID()); ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
