// UniTrade — landing page (index.php) hero background carousel.
// Rotates through assets/images/hero-1.jpg / hero-2.jpg / hero-3.jpg every
// 6 seconds. Falls back gracefully — a missing file just leaves the CSS
// gradient underneath visible, nothing breaks.

(function () {
    var heroImages = [
        'assets/images/hero-1.jpg',
        'assets/images/hero-2.png',
        'assets/images/hero-3.jpg'
    ];
    var overlay = 'linear-gradient(120deg, rgba(11,27,51,0.68) 0%, rgba(11,27,51,0.42) 48%, rgba(11,27,51,0.12) 100%)';
    var bg = document.getElementById('heroBg');
    var dotsWrap = document.getElementById('heroDots');
    // Only the background element is required — the dots are a nice-to-have
    // for multi-photo pages, not a precondition for showing the photo at all.
    if (!bg) return;

    var current = 0;

    if (dotsWrap) {
        heroImages.forEach(function (src, i) {
            var dot = document.createElement('button');
            if (i === 0) dot.className = 'active';
            dot.addEventListener('click', function () { show(i); });
            dotsWrap.appendChild(dot);
        });
    }

    function show(i) {
        current = i;
        bg.style.backgroundImage = overlay + ", url('" + heroImages[i] + "')";
        if (dotsWrap) {
            Array.prototype.forEach.call(dotsWrap.children, function (d, idx) {
                d.className = idx === i ? 'active' : '';
            });
        }
    }

   show(0);
    setInterval(function () {
        show((current + 1) % heroImages.length);
    }, 6000);
})();