(function () {
    var body = document.body;
    if (!body) return;

    body.classList.add('page-transition-enabled');
    window.requestAnimationFrame(function () {
        body.classList.add('page-ready');
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.navbar-sticky a.navbar-item');
        if (!link) return;

        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return;
        if (link.target && link.target !== '_self') return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (href.indexOf('javascript:') === 0 || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;

        var url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }

        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.hash && url.hash.charAt(0) === '#') return;

        event.preventDefault();
        body.classList.add('page-leaving');

        window.setTimeout(function () {
            window.location.href = url.href;
        }, 180);
    });
})();
