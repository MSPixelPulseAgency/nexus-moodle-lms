(() => {
    'use strict';

    const ORIGIN = window.location.origin;
    const BASE = `${ORIGIN}/local/nexusbranding`;

    const HOME_URL = `${ORIGIN}/`;
    const LOGIN_URL = `${ORIGIN}/login/index.php`;

    const HERO_IMAGES = [
        `${BASE}/pix/hero-04.png`,
        `${BASE}/pix/hero-01.png`,
        `${BASE}/pix/hero-02.png`,
        `${BASE}/pix/hero-03.png`,
    ];

    function currentPath() {
        const value =
            (window.location.pathname || '/')
                .replace(/\/+$/, '');

        return value || '/';
    }

    function isHome() {
        const value = currentPath();

        return (
            value === '/' ||
            value === '/index.php'
        );
    }

    function isLogin() {
        const value = currentPath();

        return (
            value === '/login' ||
            value === '/login/index.php'
        );
    }


    /* ==================================================
       HOMEPAGE NAVIGATION
       ================================================== */

    function installNavTitle() {
        if (!isHome()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-nav-title'
            )
        ) {
            return;
        }

        const navbar =
            document.querySelector('.navbar');

        if (!navbar) {
            return;
        }

        const title =
            document.createElement('div');

        title.className =
            'nexus-nav-title';

        title.textContent =
            'Nexus Education Private School';

        navbar.appendChild(title);
    }


    function fixLoginLinks() {
        if (!isHome()) {
            return;
        }

        document
            .querySelectorAll('a')
            .forEach(link => {

                const text =
                    (link.textContent || '')
                        .trim()
                        .toLowerCase();

                if (
                    text === 'login' ||
                    text === 'log in'
                ) {
                    link.href = LOGIN_URL;
                }
            });
    }


    /* ==================================================
       NATIVE MOOVE CAROUSEL
       ================================================== */

    function preloadHeroes() {
        HERO_IMAGES.forEach(
            (src, index) => {

                const image =
                    new Image();

                image.src = src;

                if (index === 0) {
                    image.fetchPriority =
                        'high';
                }
            }
        );
    }


    function configureMooveCarousel() {
        if (!isHome()) {
            return false;
        }

        const carousel =
            document.querySelector(
                '#moove-carousel'
            ) ||
            document.querySelector(
                '#mooveslideshow .carousel'
            );

        if (!carousel) {
            return false;
        }

        const slides =
            Array.from(
                carousel.querySelectorAll(
                    '.carousel-item'
                )
            );

        if (!slides.length) {
            return false;
        }

        slides
            .slice(0, HERO_IMAGES.length)
            .forEach(
                (slide, index) => {

                    const src =
                        HERO_IMAGES[index];

                    /*
                     * Set direct static URL as the slide
                     * background. This bypasses the broken
                     * theme stored-file URL completely.
                     */
                    slide.style.setProperty(
                        'background-image',
                        `url("${src}")`,
                        'important'
                    );

                    slide.style.setProperty(
                        'background-size',
                        'cover',
                        'important'
                    );

                    slide.style.setProperty(
                        'background-position',
                        'center center',
                        'important'
                    );

                    slide.style.setProperty(
                        'background-repeat',
                        'no-repeat',
                        'important'
                    );

                    /*
                     * Moove versions may also include an img.
                     * If one exists, force the same URL there.
                     */
                    const images =
                        slide.querySelectorAll('img');

                    images.forEach(img => {
                        img.src = src;
                        img.removeAttribute('srcset');

                        img.style.setProperty(
                            'width',
                            '100%',
                            'important'
                        );

                        img.style.setProperty(
                            'height',
                            '100%',
                            'important'
                        );

                        img.style.setProperty(
                            'object-fit',
                            'cover',
                            'important'
                        );

                        img.style.setProperty(
                            'object-position',
                            'center',
                            'important'
                        );
                    });
                }
            );

        /*
         * Guarantee slide 1 = Hero 04.
         */
        slides.forEach(
            (slide, index) => {
                slide.classList.toggle(
                    'active',
                    index === 0
                );
            }
        );

        carousel.classList.add(
            'nexus-carousel-ready'
        );

        return true;
    }


    function waitForMooveCarousel() {
        if (
            configureMooveCarousel()
        ) {
            return;
        }

        /*
         * Moodle/Moove can finish Mustache/AMD work after
         * DOMContentLoaded. Observe until carousel exists.
         */
        const observer =
            new MutationObserver(() => {

                if (
                    configureMooveCarousel()
                ) {
                    observer.disconnect();
                }
            });

        observer.observe(
            document.documentElement,
            {
                childList: true,
                subtree: true,
            }
        );

        /*
         * Safety: do not leave observer running forever.
         */
        window.setTimeout(
            () => {
                observer.disconnect();
                configureMooveCarousel();
            },
            5000
        );
    }


    /* ==================================================
       LOGIN PAGE
       ================================================== */

    function installLoginBackButton() {
        if (!isLogin()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-login-back'
            )
        ) {
            return;
        }

        const link =
            document.createElement('a');

        link.className =
            'nexus-login-back';

        link.href =
            HOME_URL;

        link.innerHTML = `
            <span aria-hidden="true">‹</span>
            <span>Back to home</span>
        `;

        document.body.prepend(link);
    }


    function installLoginBrand() {
        if (!isLogin()) {
            return;
        }

        if (
            document.querySelector(
                '.nexus-login-brand'
            )
        ) {
            return;
        }

        const container =
            document.querySelector(
                '.login-container'
            );

        if (!container) {
            return;
        }

        const brand =
            document.createElement('div');

        brand.className =
            'nexus-login-brand';

        brand.innerHTML = `
            <img
                src="${BASE}/pix/logo.png"
                alt="Nexus Education Private School"
            >

            <span class="nexus-login-eyebrow">
                LEARNING PORTAL
            </span>

            <h1>
                Welcome back
            </h1>

            <p>
                Sign in to access your courses,
                assignments and learning dashboard.
            </p>
        `;

        container.prepend(brand);
    }


    /* ==================================================
       BOOT
       ================================================== */

    function boot() {

        if (isLogin()) {
            document.body.classList.add(
                'nexus-login-page'
            );

            installLoginBackButton();
            installLoginBrand();

            return;
        }

        if (isHome()) {
            document.body.classList.add(
                'nexus-landing-page'
            );

            preloadHeroes();
            installNavTitle();
            fixLoginLinks();
            waitForMooveCarousel();
        installNexusFooter();
        }
    }


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            boot
        );
    } else {
        boot();
    }

/* ==========================================================
   NEXUS PREMIUM FOOTER
   ========================================================== */

function installNexusFooter() {
    if (!isHome()) {
        return;
    }

    const footerRoot =
        document.querySelector('#page-footer');

    if (!footerRoot) {
        return;
    }

    if (
        footerRoot.querySelector(
            '.nexus-footer-rich'
        )
    ) {
        return;
    }

    const footer =
        document.createElement('div');

    footer.className =
        'nexus-footer-rich';

    footer.innerHTML = `
        <div class="nexus-footer-rich__inner">

            <div class="nexus-footer-rich__top">

                <div class="nexus-footer-rich__brand">

                    <img
                        src="${BASE}/pix/logo.png"
                        alt="Nexus Education Private School"
                        class="nexus-footer-rich__logo"
                    >

                    <p>
                        A modern learning environment supporting
                        student achievement, confidence and future success.
                    </p>

                    <a
                        href="https://nexuseps.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="nexus-footer-rich__website"
                    >
                        Visit nexuseps.com
                    </a>

                </div>


                <div class="nexus-footer-rich__nav">

                    <div class="nexus-footer-rich__column">

                        <h3>Learning Portal</h3>

                        <a href="${HOME_URL}">
                            Home
                        </a>

                        <a href="${LOGIN_URL}">
                            Student Login
                        </a>

                    </div>


                    <div class="nexus-footer-rich__column">

                        <h3>Nexus EPS</h3>

                        <a
                            href="https://nexuseps.com"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            School Website
                        </a>

                        <a
                            href="https://nexuseps.com"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Programs
                        </a>

                        <a
                            href="https://nexuseps.com"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Contact Us
                        </a>

                    </div>

                </div>

            </div>


            <div class="nexus-footer-rich__divider"></div>


            <div class="nexus-footer-rich__bottom">

                <p>
                    © ${new Date().getFullYear()}
                    Nexus Education Private School.
                    All rights reserved.
                </p>

                <div class="nexus-footer-rich__social">

                    <a
                        href="https://nexuseps.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Nexus EPS website"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M3 12h18"></path>
                            <path d="M12 3a15 15 0 0 1 0 18"></path>
                            <path d="M12 3a15 15 0 0 0 0 18"></path>
                        </svg>
                    </a>

                </div>

            </div>

        </div>
    `;

    footerRoot.innerHTML = '';

    footerRoot.appendChild(
        footer
    );
}

})();

/* NEXUS-HOMEPAGE-VIDEO-SLIDE-START */
(function () {
    'use strict';

    function addNexusHomepageVideoSlide() {
        const body = document.body;

        if (!body) {
            return;
        }

        // Homepage only.
        const isHomepage =
            body.id === 'page-site-index' ||
            body.classList.contains('path-site');

        if (!isHomepage) {
            return;
        }

        // Prevent duplicate insertion.
        if (document.getElementById('nexus-homepage-video-slide')) {
            return;
        }

        const carousels = Array.from(
            document.querySelectorAll('.carousel')
        );

        const carousel = carousels.find((item) => {
            const inner = item.querySelector('.carousel-inner');
            return inner && inner.querySelectorAll('.carousel-item').length >= 2;
        });

        if (!carousel) {
            return;
        }

        const inner = carousel.querySelector('.carousel-inner');

        if (!inner) {
            return;
        }

        const oldActive = inner.querySelector('.carousel-item.active');

        if (oldActive) {
            oldActive.classList.remove('active');
        }

        const slide = document.createElement('div');

        slide.id = 'nexus-homepage-video-slide';
        slide.className = 'carousel-item active nexus-video-carousel-item';

        // Give the video slide a little longer than image slides.
        slide.setAttribute('data-bs-interval', '12000');
        slide.setAttribute('data-interval', '12000');

        slide.innerHTML = `
            <video
                class="nexus-homepage-carousel-video"
                autoplay
                muted
                loop
                playsinline
                preload="metadata"
                aria-label="Nexus Education Private School introduction video"
            >
                <source
                    src="/theme/moove/media/homepage.mp4"
                    type="video/mp4"
                >
            </video>
        `;

        inner.insertBefore(slide, inner.firstChild);

        // Add a new first indicator while preserving the existing 4.
        const indicators =
            carousel.querySelector('.carousel-indicators');

        if (indicators) {
            const oldIndicators =
                Array.from(indicators.children);

            oldIndicators.forEach((indicator, index) => {
                indicator.classList.remove('active');
                indicator.removeAttribute('aria-current');

                if (indicator.hasAttribute('data-bs-slide-to')) {
                    indicator.setAttribute(
                        'data-bs-slide-to',
                        String(index + 1)
                    );
                }

                if (indicator.hasAttribute('data-slide-to')) {
                    indicator.setAttribute(
                        'data-slide-to',
                        String(index + 1)
                    );
                }
            });

            const indicator =
                document.createElement('button');

            indicator.type = 'button';
            indicator.className = 'active';
            indicator.setAttribute('aria-current', 'true');
            indicator.setAttribute(
                'aria-label',
                'Video slide'
            );

            if (carousel.id) {
                indicator.setAttribute(
                    'data-bs-target',
                    '#' + carousel.id
                );

                indicator.setAttribute(
                    'data-target',
                    '#' + carousel.id
                );
            }

            indicator.setAttribute(
                'data-bs-slide-to',
                '0'
            );

            indicator.setAttribute(
                'data-slide-to',
                '0'
            );

            indicators.insertBefore(
                indicator,
                indicators.firstChild
            );
        }

        const video =
            slide.querySelector('video');

        if (video) {
            video.muted = true;

            const playVideo = () => {
                video.play().catch(() => {});
            };

            playVideo();

            carousel.addEventListener(
                'slid.bs.carousel',
                () => {
                    if (slide.classList.contains('active')) {
                        playVideo();
                    } else {
                        video.pause();
                    }
                }
            );
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            addNexusHomepageVideoSlide,
            {once: true}
        );
    } else {
        addNexusHomepageVideoSlide();
    }
})();
 /* NEXUS-HOMEPAGE-VIDEO-SLIDE-END */

/* NEXUS-HOMEPAGE-VIDEO-SOUND-START */
(function () {
    'use strict';

    function initNexusVideoSound() {
        const video =
            document.querySelector(
                '#nexus-homepage-video-slide .nexus-homepage-carousel-video'
            );

        const slide =
            document.getElementById(
                'nexus-homepage-video-slide'
            );

        if (!video || !slide) {
            return;
        }

        if (
            document.getElementById(
                'nexus-homepage-video-sound'
            )
        ) {
            return;
        }

        video.muted = true;
        video.volume = 1;

        const button =
            document.createElement('button');

        button.id =
            'nexus-homepage-video-sound';

        button.type =
            'button';

        button.className =
            'nexus-homepage-video-sound';

        button.setAttribute(
            'aria-label',
            'Turn video sound on'
        );

        button.innerHTML = `
            <span class="nexus-sound-icon">🔇</span>
            <span class="nexus-sound-label">Sound on</span>
        `;

        slide.appendChild(button);

        button.addEventListener(
            'click',
            async () => {
                try {
                    video.muted = !video.muted;

                    if (!video.muted) {
                        video.volume = 1;
                        await video.play();

                        button.innerHTML = `
                            <span class="nexus-sound-icon">🔊</span>
                            <span class="nexus-sound-label">Sound off</span>
                        `;

                        button.setAttribute(
                            'aria-label',
                            'Turn video sound off'
                        );
                    } else {
                        button.innerHTML = `
                            <span class="nexus-sound-icon">🔇</span>
                            <span class="nexus-sound-label">Sound on</span>
                        `;

                        button.setAttribute(
                            'aria-label',
                            'Turn video sound on'
                        );
                    }
                } catch (error) {
                    console.warn(
                        'Nexus video audio could not start:',
                        error
                    );
                }
            }
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initNexusVideoSound,
            {once: true}
        );
    } else {
        initNexusVideoSound();
    }
})();
 /* NEXUS-HOMEPAGE-VIDEO-SOUND-END */

/* NEXUS-CROSS-BROWSER-VIDEO-AUDIO-START */
(function () {
    'use strict';

    const VIDEO_SELECTOR =
        '#nexus-homepage-video-slide .nexus-homepage-carousel-video';

    const SLIDE_SELECTOR =
        '#nexus-homepage-video-slide';

    const BUTTON_ID =
        'nexus-homepage-video-sound';

    function updateButton(button, video) {
        const isMuted = video.muted;

        button.setAttribute(
            'aria-label',
            isMuted ? 'Play video with sound' : 'Mute video'
        );

        button.innerHTML = isMuted
            ? '<span aria-hidden="true">🔇</span><span>Sound on</span>'
            : '<span aria-hidden="true">🔊</span><span>Sound off</span>';
    }

    function mountSoundButton() {
        const slide = document.querySelector(SLIDE_SELECTOR);
        const video = document.querySelector(VIDEO_SELECTOR);

        if (!slide || !video) {
            return false;
        }

        if (document.getElementById(BUTTON_ID)) {
            return true;
        }

        slide.style.position = 'relative';

        video.muted = true;
        video.defaultMuted = true;
        video.volume = 1;

        const button = document.createElement('button');

        button.id = BUTTON_ID;
        button.type = 'button';
        button.className = 'nexus-homepage-video-sound';
        button.setAttribute('aria-live', 'polite');

        updateButton(button, video);

        button.addEventListener('click', async function (event) {
            event.preventDefault();
            event.stopPropagation();

            try {
                if (video.muted) {
                    video.muted = false;
                    video.defaultMuted = false;
                    video.volume = 1;

                    await video.play();
                } else {
                    video.muted = true;
                }

                updateButton(button, video);
            } catch (error) {
                console.error(
                    'Nexus hero audio playback failed:',
                    error
                );

                video.muted = true;
                updateButton(button, video);
            }
        });

        slide.appendChild(button);

        return true;
    }

    function init() {
        if (mountSoundButton()) {
            return;
        }

        const observer = new MutationObserver(function () {
            if (mountSoundButton()) {
                observer.disconnect();
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true
        });

        window.setTimeout(function () {
            observer.disconnect();
        }, 15000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            init,
            { once: true }
        );
    } else {
        init();
    }
})();
/* NEXUS-CROSS-BROWSER-VIDEO-AUDIO-END */
