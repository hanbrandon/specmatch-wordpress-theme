<footer class="site-footer">
    <div class="shell">
        <div class="footer-top">
            <a class="footer-brand" href="<?php echo esc_url(home_url('/')); ?>"><span>SPEC</span>MATCH<small>KR</small></a>
            <p>광고 문구가 아니라 숫자로 판단할 수 있도록.<br>기기와 부품의 차이를 한국어로 정리합니다.</p>
        </div>
        <div class="footer-grid">
            <nav aria-label="제품 카테고리">
                <strong>데이터베이스</strong>
                <a href="<?php echo esc_url(ps_catalog_archive_url('phone')); ?>">스마트폰</a>
                <a href="<?php echo esc_url(ps_catalog_archive_url('laptop')); ?>">노트북</a>
                <a href="<?php echo esc_url(ps_catalog_archive_url('cpu')); ?>">CPU</a>
                <a href="<?php echo esc_url(ps_catalog_archive_url('gpu')); ?>">GPU</a>
            </nav>
            <nav aria-label="도구">
                <strong>도구</strong>
                <a href="<?php echo esc_url(home_url('/compare/')); ?>">기기 비교</a>
                <a href="<?php echo esc_url(home_url('/?s=')); ?>">통합 검색</a>
                <a href="<?php echo esc_url(ps_catalog_archive_url('phone')); ?>">브랜드 찾기</a>
            </nav>
            <div>
                <strong>안내</strong>
                <p>일부 구매 링크를 통해 수수료를 받을 수 있으며 구매 가격에는 영향을 주지 않습니다.</p>
                <nav class="footer-policy-links" aria-label="운영 및 정책">
                    <a href="<?php echo esc_url(home_url('/about/')); ?>">사이트 소개</a>
                    <a href="<?php echo esc_url(home_url('/methodology/')); ?>">데이터·평가 방법</a>
                    <a href="<?php echo esc_url(home_url('/corrections/')); ?>">오류 제보</a>
                    <a href="<?php echo esc_url(home_url('/affiliate-disclosure/')); ?>">제휴 링크 고지</a>
                    <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">개인정보처리방침</a>
                </nav>
            </div>
        </div>
        <div class="footer-meta">
            <span>© <?php echo esc_html(wp_date('Y')); ?> SPECMATCH</span>
            <span>제품 정보 · 성능 측정 · 비교</span>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
