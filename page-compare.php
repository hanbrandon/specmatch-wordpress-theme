<?php
/*
Template Name: 휴대폰 비교 선택기
*/
get_header();
?>
<main class="site-main shell" id="main-content">
    <?php ps_breadcrumbs(true); ?>
    <header class="compare-hero">
        <p class="eyebrow">제품 비교 만들기</p>
        <h1>두 기기를<br>나란히 놓기.</h1>
        <p>카테고리를 선택한 뒤 제품명 두 개를 검색해 비교하세요.</p>
    </header>
    <nav class="compare-categories" data-compare-categories aria-label="비교 카테고리">
        <?php foreach (['phone' => '스마트폰', 'laptop' => '노트북', 'cpu' => 'CPU', 'gpu' => 'GPU', 'ssd' => 'SSD'] as $type => $type_label) : ?>
            <button type="button" class="<?php echo $type === 'phone' ? 'is-active' : ''; ?>" data-compare-type="<?php echo esc_attr($type); ?>"><?php echo esc_html($type_label); ?></button>
        <?php endforeach; ?>
    </nav>
    <div class="compare-keywords" data-compare-keywords aria-label="추천 검색어">
        <span>추천 키워드</span>
        <?php foreach (['Samsung', 'Apple', 'Google', 'Xiaomi', 'Huawei', 'LG'] as $keyword) : ?>
            <button type="button" data-keyword="<?php echo esc_attr($keyword); ?>"><?php echo esc_html($keyword); ?></button>
        <?php endforeach; ?>
    </div>
    <section class="compare-builder" data-compare-builder data-current-type="phone">
        <?php foreach (['a' => '첫 번째 기기', 'b' => '두 번째 기기'] as $side => $label) : ?>
            <div class="device-picker" data-picker="<?php echo esc_attr($side); ?>">
                <span>0<?php echo $side === 'a' ? '1' : '2'; ?></span>
                <label for="phone-<?php echo esc_attr($side); ?>"><?php echo esc_html($label); ?></label>
                <input id="phone-<?php echo esc_attr($side); ?>" type="search" autocomplete="off" placeholder="모델명 입력">
                <input type="hidden" data-selected-slug>
                <div class="picker-results" data-results hidden></div>
            </div>
        <?php endforeach; ?>
        <button class="button button--acid compare-submit" type="button" data-compare-submit disabled>비교 시작 →</button>
    </section>
</main>
<?php get_footer(); ?>
