<?php
get_header();
$catalogs = [
    ['phone', '01', '스마트폰', '화면, 카메라, 배터리까지', '스마트폰'],
    ['laptop', '02', '노트북', '성능과 휴대성의 균형', '노트북'],
    ['cpu', '03', 'CPU', '연산 성능과 전력 효율', '프로세서'],
    ['gpu', '04', 'GPU', '그래픽 성능과 메모리', '그래픽카드'],
];
$total_count = array_sum(array_map(static fn(array $item): int => ps_catalog_count($item[0]), $catalogs));
?>
<main class="site-main" id="main-content">
    <section class="home-hero shell">
        <div class="hero-copy">
            <p class="eyebrow">독립 기술 데이터베이스 / 한국</p>
            <h1>기기의 차이를<br><em>데이터로.</em></h1>
            <p>스마트폰부터 노트북, CPU와 GPU까지. 복잡한 사양과 벤치마크를 같은 언어로 정리합니다.</p>
            <div class="hero-actions">
                <a class="button" href="#catalog-index">데이터베이스 탐색</a>
                <a href="<?php echo esc_url(home_url('/compare/')); ?>">스마트폰 비교하기 <span>↗</span></a>
            </div>
        </div>
        <div class="hero-index">
            <span>실시간 제품 현황</span>
            <strong><?php echo esc_html(number_format_i18n($total_count)); ?></strong>
            <p>검증 가능한 제품 프로필</p>
            <dl>
                <?php foreach ($catalogs as [$type, $number, $title]) : ?>
                    <div><dt><?php echo esc_html(strtoupper($type)); ?></dt><dd><?php echo esc_html(number_format_i18n(ps_catalog_count($type))); ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <section class="finder shell">
        <div>
            <span>⌕</span>
            <p><strong>통합 검색</strong> 제품명, 칩셋, 그래픽카드를 검색하세요.</p>
        </div>
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" data-catalog-search>
            <input type="search" name="s" placeholder="예: Galaxy S25, MacBook Pro, Ryzen 9, RTX 5090" aria-label="통합 제품 검색" autocomplete="off" data-search-input>
            <button type="submit">검색</button>
            <div class="search-suggestions" data-search-suggestions hidden></div>
        </form>
    </section>

    <section class="catalog-index shell" id="catalog-index">
        <header class="editorial-heading">
            <div><span>제품 데이터베이스 / 01—04</span><h2>하나의 기준,<br>네 가지 제품군.</h2></div>
            <p>제품군마다 다른 숫자를 억지로 섞지 않습니다. 각 카테고리에서 실제 선택에 필요한 항목만 남깁니다.</p>
        </header>
        <div class="catalog-panels">
            <?php foreach ($catalogs as [$type, $number, $title, $description, $english]) : ?>
                <a class="catalog-panel catalog-panel--<?php echo esc_attr($type); ?>" href="<?php echo esc_url(ps_catalog_archive_url($type)); ?>">
                    <span><?php echo esc_html($number); ?></span>
                    <small><?php echo esc_html($english); ?></small>
                    <h3><?php echo esc_html($title); ?></h3>
                    <p><?php echo esc_html($description); ?></p>
                    <strong><?php echo esc_html(number_format_i18n(ps_catalog_count($type))); ?><i>개 제품</i></strong>
                    <b>↗</b>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="home-catalog shell">
        <div class="section-heading home-index-heading">
            <div><p class="eyebrow">전체 데이터베이스</p><h2>카테고리별 주요 제품</h2></div>
            <p>각 데이터베이스에서 최근 기기와 높은 평가를 받은 제품을 빠르게 확인하세요.</p>
        </div>
        <div class="home-index-grid">
            <?php ps_home_catalog_list('phone', '스마트폰', '최신 스마트폰'); ?>
            <?php ps_home_catalog_list('laptop', '노트북', '주요 노트북'); ?>
            <?php ps_home_catalog_list('cpu', 'CPU', '주요 프로세서'); ?>
            <?php ps_home_catalog_list('gpu', 'GPU', '주요 그래픽카드'); ?>
        </div>
        <aside class="home-widget-strip" aria-label="인기 및 최신 스마트폰">
            <?php ps_phone_sidebar_widget('많이 찾는 폰', 'popular', pc_sidebar_phone_posts('popular', 5)); ?>
            <?php ps_phone_sidebar_widget('최신 폰', 'newest', pc_sidebar_phone_posts('newest', 5)); ?>
            <?php ps_sidebar_ad_slot(); ?>
        </aside>
    </section>
</main>
<?php get_footer(); ?>
