<?php
get_header();
$device = pc_get_device((int) get_the_ID());
$spec_groups = $device ? pc_group_specs(pc_get_specs((int) $device->id)) : [];
$offers = $device ? pc_get_offers((int) $device->id) : [];
$insights = $device ? pc_device_insights($device) : [];
$related = $device ? pc_related_devices($device) : [];
$priority_comparisons = $device ? pc_priority_comparisons_for_device($device, 4) : [];
$popular_sidebar = $device ? pc_sidebar_phone_posts('popular', 5, (int) $device->post_id) : [];
$newest_sidebar = $device ? pc_sidebar_phone_posts('newest', 5, (int) $device->post_id) : [];
?>
<main class="site-main" id="main-content">
    <?php ps_breadcrumbs(); ?>
    <?php while (have_posts()) : the_post(); ?>
        <article>
            <header class="phone-hero shell">
                <div class="phone-title">
                    <p class="eyebrow"><?php echo esc_html($device?->brand ?: '스마트폰'); ?> / 전체 사양</p>
                    <h1><?php the_title(); ?></h1>
                    <?php $original_name = pc_product_original_name((int) get_the_ID()); ?>
                    <?php if ($original_name && $original_name !== get_the_title()) : ?><p class="product-original-name"><?php echo esc_html($original_name); ?></p><?php endif; ?>
                    <?php if (has_excerpt()) : ?><p class="lede"><?php echo esc_html(pc_public_text(get_the_excerpt())); ?></p><?php endif; ?>
                    <a class="button detail-compare-button" href="<?php echo esc_url(add_query_arg([
                        'phone' => get_post_field('post_name', get_the_ID()),
                        'name' => get_the_title(),
                        'post_id' => get_the_ID(),
                    ], home_url('/compare/'))); ?>">이 제품과 비교하기 →</a>
                    <?php ps_product_series_link((int) get_the_ID()); ?>
                </div>
                <div class="phone-visual">
                    <?php if (pc_public_image_url($device)) : ?>
                        <img loading="eager" fetchpriority="high" decoding="async" width="540" height="540" src="<?php echo esc_url(pc_public_image_url($device)); ?>" alt="<?php the_title_attribute(); ?>">
                    <?php else : ?><strong>이미지 없음</strong><?php endif; ?>
                </div>
            </header>

            <?php if ($device) : ?>
                <section class="quick-specs shell">
                    <?php
                    $quick = [
                        '출시' => pc_public_text($device->announced),
                        '화면' => $device->display,
                        '칩셋' => $device->chipset,
                        '메모리' => $device->ram,
                        '저장공간' => $device->storage,
                        '카메라' => $device->camera,
                        '배터리' => $device->battery,
                        '운영체제' => $device->os,
                    ];
                    foreach ($quick as $label => $value) :
                        if (!$value) continue;
                    ?>
                        <div class="quick-spec"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html(pc_public_text((string) $value)); ?></strong></div>
                    <?php endforeach; ?>
                </section>

                <section class="device-insights shell">
                    <div class="insight-summary">
                        <p class="eyebrow">핵심 요약 / 자동 분석</p>
                        <h2>이 기기, 한눈에 보기</h2>
                        <p><?php echo esc_html($insights['summary']); ?></p>
                    </div>
                    <?php if ($insights['pros'] || $insights['cons']) : ?>
                        <div class="pros-cons">
                            <div class="pros">
                                <span class="insight-number">+</span>
                                <h3>확인되는 장점</h3>
                                <ul>
                                    <?php foreach ($insights['pros'] as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?>
                                    <?php if (!$insights['pros']) : ?><li>명확한 우위 항목은 전체 스펙을 확인하세요.</li><?php endif; ?>
                                </ul>
                            </div>
                            <div class="cons">
                                <span class="insight-number">−</span>
                                <h3>확인할 제한 사항</h3>
                                <ul>
                                    <?php foreach ($insights['cons'] as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?>
                                    <?php if (!$insights['cons']) : ?><li>원본 데이터에서 뚜렷한 제한 사항이 확인되지 않았습니다.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($insights['recommended']) : ?>
                        <div class="recommended-for">
                            <strong>이런 사용자에게 적합</strong>
                            <?php foreach ($insights['recommended'] as $item) : ?><span><?php echo esc_html($item); ?></span><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="device-updated">마지막 데이터 갱신: <?php echo esc_html(get_the_modified_date('Y년 n월 j일')); ?></p>
                </section>

                <div class="content-layout shell">
                    <aside class="content-rail">
                        <nav class="page-index" aria-label="이 페이지의 스펙 목차">
                            <p class="eyebrow">이 페이지에서 확인할 내용</p>
                            <?php foreach (array_keys($spec_groups) as $section) : ?>
                                <a href="#spec-<?php echo esc_attr(sanitize_title($section)); ?>"><?php echo esc_html(pc_translate_spec_section($section)); ?></a>
                            <?php endforeach; ?>
                        </nav>
                        <?php ps_phone_sidebar_widget('많이 찾는 폰', 'popular', $popular_sidebar); ?>
                        <?php ps_phone_sidebar_widget('최신 폰', 'newest', $newest_sidebar); ?>
                        <?php ps_sidebar_ad_slot(); ?>
                    </aside>
                    <div>
                        <?php if ($offers) : ?>
                            <section class="offers">
                                <div class="section-heading"><div><p class="eyebrow">구매 정보 / 원화</p><h2>구매 가격</h2></div></div>
                                <?php foreach ($offers as $offer) : ?>
                                    <a class="button button--acid" rel="sponsored nofollow" data-track-event="affiliate" data-track-post="<?php echo esc_attr((string) get_the_ID()); ?>" href="<?php echo esc_url($offer->affiliate_url); ?>">
                                        <?php echo esc_html($offer->merchant); ?> ·
                                        <?php echo $offer->price ? esc_html(number_format_i18n($offer->price) . '원') : '가격 확인'; ?>
                                    </a>
                                <?php endforeach; ?>
                                <p><small>제휴 링크를 통해 구매 시 수수료를 받을 수 있습니다.</small></p>
                            </section>
                        <?php endif; ?>

                        <section class="spec-sheet">
                            <?php foreach ($spec_groups as $section => $rows) : ?>
                                <h2 id="spec-<?php echo esc_attr(sanitize_title($section)); ?>"><?php echo esc_html(pc_translate_spec_section($section)); ?></h2>
                                <?php foreach ($rows as $row) : ?>
                                    <div class="spec-row">
                                        <?php ps_spec_label($section, $row->field_name, 'spec-help-' . (int) $row->id); ?>
                                        <span><?php echo esc_html($row->field_value ? pc_public_text((string) $row->field_value) : '—'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </section>

                        <?php if ($insights['faqs']) : ?>
                            <section class="device-faq">
                                <div class="section-heading">
                                    <div><p class="eyebrow">사양 관련 질문</p><h2>자주 묻는 질문</h2></div>
                                </div>
                                <?php foreach ($insights['faqs'] as $faq) : ?>
                                    <details>
                                        <summary><?php echo esc_html($faq['question']); ?></summary>
                                        <p><?php echo esc_html($faq['answer']); ?></p>
                                    </details>
                                <?php endforeach; ?>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="shell"><?php ps_product_connections((int) get_the_ID()); ?></div>

                <?php if ($priority_comparisons) : ?>
                    <section class="priority-comparisons shell">
                        <div class="section-heading">
                            <div><p class="eyebrow">많이 비교하는 조합</p><h2><?php the_title(); ?> 비교</h2></div>
                        </div>
                        <div class="priority-comparisons__list">
                            <?php foreach ($priority_comparisons as $comparison) : ?>
                                <a href="<?php echo esc_url(pc_compare_url($device, $comparison)); ?>">
                                    <span><?php echo esc_html(pc_product_name((int) $device->post_id)); ?></span>
                                    <b>vs</b>
                                    <strong><?php echo esc_html(pc_product_name((int) $comparison->post_id)); ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($related) : ?>
                    <section class="related-phones shell">
                        <div class="section-heading">
                            <div><p class="eyebrow">관련 제품 더보기</p><h2>같은 브랜드의 관련 기기</h2></div>
                        </div>
                        <div class="phone-grid">
                            <?php foreach ($related as $index => $related_device) : ?>
                                <?php ps_phone_card(get_post((int) $related_device->post_id), $index + 1); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                <div class="shell"><?php ps_recent_products_section((int) get_the_ID()); ?></div>
            <?php endif; ?>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
