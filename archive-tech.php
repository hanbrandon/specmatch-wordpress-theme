<?php
get_header();
$type = (string) get_query_var('post_type');
$config = [
    'laptop' => ['노트북', '휴대성과 성능, 화면과 배터리를 같은 기준으로 비교합니다.', '노트북 데이터베이스'],
    'cpu' => ['CPU', '코어 구성부터 실제 벤치마크와 전력 효율까지 정리합니다.', '프로세서 데이터베이스'],
    'gpu' => ['GPU', '그래픽 성능, 메모리, 소비전력을 데이터로 읽습니다.', '그래픽카드 데이터베이스'],
    'ssd' => ['SSD', '용량, 인터페이스, 컨트롤러, NAND와 성능을 같은 기준으로 비교합니다.', 'SSD 데이터베이스'],
][$type] ?? ['하드웨어', '성능과 사양을 비교합니다.', '하드웨어 데이터베이스'];
$active_brand = sanitize_text_field((string) get_query_var('tech_brand'));
$archive_title = $active_brand ? ucwords(str_replace('-', ' ', $active_brand)) . ' ' . $config[0] : $config[0];
$ssd_landing = $type === 'ssd' ? sanitize_key((string) get_query_var('ssd_landing')) : '';
if ($ssd_landing && function_exists('ps_ssd_landing_labels')) {
    $ssd_landing_labels = ps_ssd_landing_labels();
    if (isset($ssd_landing_labels[$ssd_landing])) {
        $archive_title = $ssd_landing_labels[$ssd_landing];
        $config[1] = $archive_title . ' 제품을 최신 출시일 순서로 살펴보고 핵심 사양을 비교합니다.';
        $ssd_landing_copy = [
            '1tb' => '운영체제와 주요 게임을 함께 설치하기 좋은 대표 용량입니다. 인터페이스와 NAND, 내구성을 함께 비교하세요.',
            '2tb' => '대용량 게임과 작업 파일을 넉넉하게 보관하려는 사용자에게 적합한 용량입니다.',
            '4tb' => '고용량 저장공간이 필요한 영상 작업과 대규모 게임 라이브러리를 위한 제품군입니다.',
            'nvme-gen4' => 'PCIe 4.0 시스템에서 속도와 발열, 방열판 필요 여부를 함께 확인해야 합니다.',
            'nvme-gen5' => '최고 수준의 순차 속도를 제공하지만 발열과 시스템 호환성을 우선 확인해야 합니다.',
            'sata' => '기존 PC와 노트북 업그레이드에 널리 쓰이며 폼팩터와 커넥터 호환성이 중요합니다.',
            'ps5-compatible' => 'PS5 요구 성능과 장착 규격을 충족한다고 표시된 제품입니다. 방열판 조건도 확인하세요.',
            'tlc' => '일반적으로 지속 쓰기와 내구성의 균형을 중시할 때 선택하는 NAND 구성입니다.',
            'qlc' => '고용량 구성에 유리하지만 장시간 쓰기 성능과 내구성 수치를 함께 비교해야 합니다.',
            'dram' => '별도 DRAM을 사용하는 제품은 주소 매핑과 지속적인 작업 성능에서 유리할 수 있습니다.',
            'hmb' => '시스템 메모리를 활용하는 HMB 방식은 DRAM 없는 NVMe SSD의 효율적인 대안입니다.',
            'high-endurance' => '표기 내구성 1,000 TBW 이상 제품을 모았습니다. 용량당 TBW와 보증기간도 함께 확인하세요.',
        ][$ssd_landing] ?? '';
    }
}
if ($active_brand) {
    $config[1] = $archive_title . '의 최신 제품, 평가 점수와 전체 사양을 출시일순으로 정리합니다.';
}
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <header class="catalog-archive-hero">
        <p><?php echo esc_html($config[2]); ?></p>
        <h1><?php echo esc_html($archive_title); ?></h1>
        <div>
            <strong><?php echo esc_html(number_format_i18n((int) $wp_query->found_posts)); ?></strong>
            <span><?php echo esc_html($config[1]); ?></span>
        </div>
    </header>
    <?php ps_category_brand_filter($type); ?>
    <?php if ($type === 'ssd') : ?>
        <?php if (!empty($ssd_landing_copy)) : ?><section class="ssd-landing-intro"><strong><?php echo esc_html($archive_title); ?> 선택 기준</strong><p><?php echo esc_html($ssd_landing_copy); ?></p></section><?php endif; ?>
        <nav class="ssd-landing-links" aria-label="SSD 용도와 규격별 보기">
            <span>빠른 탐색</span>
            <?php foreach (ps_ssd_landing_labels() as $slug => $landing_label) : ?>
                <a class="<?php echo $ssd_landing === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url(home_url('/ssds/' . $slug . '/')); ?>"><?php echo esc_html($landing_label); ?></a>
            <?php endforeach; ?>
        </nav>
        <section class="ssd-archive-guide" aria-label="SSD 선택 가이드">
            <div><span>01</span><strong>인터페이스</strong><p>NVMe는 높은 전송 속도가 필요한 작업에, SATA는 기존 PC와 노트북 업그레이드에 주로 사용됩니다.</p></div>
            <div><span>02</span><strong>NAND와 캐시</strong><p>TLC·QLC 같은 NAND 종류와 DRAM·HMB 구성은 지속 쓰기 성능과 사용 특성에 영향을 줍니다.</p></div>
            <div><span>03</span><strong>속도와 내구성</strong><p>순차 속도뿐 아니라 랜덤 성능, TBW, 보증 기간을 함께 확인하면 용도에 맞는 제품을 고르기 쉽습니다.</p></div>
        </section>
    <?php endif; ?>
    <div class="archive-catalog-layout">
        <div class="archive-catalog-main">
            <?php ps_catalog_tools($type); ?>
            <?php if (have_posts()) : ?>
                <section class="tech-grid" data-catalog-view>
                    <?php
                    $rank = ((max(1, (int) get_query_var('paged')) - 1) * (int) get_query_var('posts_per_page')) + 1;
                    while (have_posts()) :
                        the_post();
                        ps_tech_card(get_post(), $rank++);
                    endwhile;
                    ?>
                </section>
                <nav class="pagination"><?php the_posts_pagination(['mid_size' => 2, 'prev_text' => '←', 'next_text' => '→']); ?></nav>
            <?php else : ?>
                <section class="catalog-loading">
                    <span>데이터 수집 진행 중</span>
                    <h2>데이터를 정리하고 있습니다.</h2>
                    <p>수집이 끝나는 대로 벤치마크, 핵심 사양, 비교 기능을 순차적으로 공개합니다.</p>
                    <div><i></i><i></i><i></i><i></i></div>
                </section>
            <?php endif; ?>
        </div>
        <aside class="archive-side-widgets" aria-label="<?php echo esc_attr($config[0]); ?> 인기 및 최신 제품">
            <?php
            $widget_brand_name = $active_brand ? ucwords(str_replace('-', ' ', $active_brand)) : '';
            ps_tech_sidebar_widget(
                ($widget_brand_name ? $widget_brand_name . ' ' : '') . '높은 평가 제품',
                'popular',
                $type,
                ps_tech_sidebar_posts($type, 'popular', 5, $active_brand)
            );
            ps_tech_sidebar_widget(
                ($widget_brand_name ? $widget_brand_name . ' ' : '최신 ') . ($widget_brand_name ? '최신 ' . $config[0] : $config[0]),
                'newest',
                $type,
                ps_tech_sidebar_posts($type, 'newest', 5, $active_brand)
            );
            ?>
            <?php ps_sidebar_ad_slot(); ?>
        </aside>
    </div>
</main>
<?php get_footer(); ?>
