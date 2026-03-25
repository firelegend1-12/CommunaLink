            </main>
        </div>
        <?php include 'bottom-nav.php'; ?>
    </div>
<script>
// Mobile scroll fix: force inline styles that override all CSS (highest specificity)
(function() {
    if (window.innerWidth > 767) return;
    var s = function(el, props) {
        if (!el) return;
        for (var k in props) el.style.setProperty(k, props[k], 'important');
    };
    s(document.documentElement, { 'height': 'auto', 'overflow-y': 'auto', 'overflow-x': 'hidden' });
    s(document.body, { 'height': 'auto', 'overflow-y': 'auto', 'overflow-x': 'hidden', 'touch-action': 'manipulation' });
    s(document.querySelector('.page-container'), { 'display': 'block', 'height': 'auto', 'min-height': 'auto', 'overflow': 'visible' });
    s(document.querySelector('.main-content'), { 'display': 'block', 'height': 'auto', 'min-height': 'auto', 'overflow': 'visible' });
    s(document.querySelector('.page-main'), { 'height': 'auto', 'min-height': 'auto', 'overflow': 'visible' });
})();
</script>
</body>
</html> 