<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="naver-site-verification" content="0ff82af8457334692dfeb9acf9c1a9b75e09157b">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9481966710896505" crossorigin="anonymous"></script>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content">본문으로 바로가기</a>
<header class="site-header">
    <div class="header-inner shell">
        <a class="brand-mark" href="<?php echo esc_url(home_url('/')); ?>">
            <span>SPEC</span>MATCH<small>KR</small>
        </a>
        <nav class="site-nav" id="primary-menu" aria-label="주 메뉴" data-mobile-menu>
            <ul>
                <?php
                $catalog_menu = [
                    'phone' => ['스마트폰', '스마트폰 브랜드', '브랜드별 스마트폰', '제조사를 선택해 출시된 기기를 빠르게 찾아보세요.'],
                    'laptop' => ['노트북', '노트북 브랜드', '브랜드별 노트북', '제조사별 노트북을 한 번에 확인하세요.'],
                    'cpu' => ['CPU', '프로세서 브랜드', '브랜드별 CPU', '제조사별 프로세서를 빠르게 찾아보세요.'],
                    'gpu' => ['GPU', '그래픽카드 브랜드', '브랜드별 GPU', '제조사별 그래픽 프로세서를 확인하세요.'],
                    'ssd' => ['SSD', 'SSD 브랜드', '브랜드별 SSD', '제조사별 SSD와 용량별 사양을 확인하세요.'],
                ];
                foreach ($catalog_menu as $type => [$menu_label, $eyebrow, $heading, $description]) :
                    $brands = ps_catalog_brands($type);
                    $current = is_post_type_archive($type) || is_singular($type) || ($type === 'phone' && is_tax('phone_brand'));
                ?>
                <li class="site-nav__catalog<?php echo $current ? ' is-current' : ''; ?>">
                    <div class="site-nav__catalog-trigger">
                        <button type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($type); ?>-brand-menu" aria-label="<?php echo esc_attr($menu_label); ?> 브랜드 메뉴 열기" data-brand-toggle data-label="<?php echo esc_attr($menu_label); ?>">
                            <strong><?php echo esc_html($menu_label); ?></strong>
                            <span class="nav-chevron" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="brand-mega<?php echo count($brands) > 8 ? ' brand-mega--wide' : ''; ?>" id="<?php echo esc_attr($type); ?>-brand-menu" data-brand-menu>
                        <div class="brand-mega__intro">
                            <span><?php echo esc_html($eyebrow); ?></span>
                            <strong><?php echo esc_html($heading); ?></strong>
                            <p><?php echo esc_html($description); ?></p>
                        </div>
                        <div class="brand-mega__links">
                            <?php foreach ($brands as $brand) : ?>
                                <a href="<?php echo esc_url($brand['url']); ?>">
                                    <span><?php echo esc_html($brand['name']); ?><small><?php echo esc_html(number_format_i18n($brand['count'])); ?></small></span>
                                </a>
                            <?php endforeach; ?>
                            <a class="brand-mega__all" href="<?php echo esc_url(ps_catalog_archive_url($type)); ?>">
                                <span>전체 <?php echo esc_html($menu_label); ?></span>
                            </a>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="header-actions">
            <a class="header-compare" href="<?php echo esc_url(home_url('/compare/')); ?>">비교</a>
            <button class="header-search" type="button" aria-label="통합 검색 열기" aria-expanded="false" data-header-search-toggle><span>검색</span><b>⌕</b></button>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" aria-label="메뉴 열기" data-menu-toggle>
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>
<div class="header-search-panel" data-header-search-panel hidden>
    <form class="shell" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" data-catalog-search>
        <label for="header-search-query">통합 제품 검색</label>
        <div>
            <input id="header-search-query" type="search" name="s" placeholder="제품명, 칩셋, 그래픽카드 검색" autocomplete="off" data-search-input>
            <button type="submit">검색</button>
        </div>
        <div class="search-suggestions" data-search-suggestions hidden></div>
    </form>
</div>
