(function () {
    'use strict';

    function initReviewSlider(wrap) {

        var track = wrap.querySelector('.rs-track');
        var prevBtn = wrap.querySelector('.rs-nav-prev');
        var nextBtn = wrap.querySelector('.rs-nav-next');
        var dotsWrap = wrap.querySelector('.rs-dots');

        if (!track || !prevBtn || !nextBtn || !dotsWrap) {
            return;
        }

        var cards = Array.prototype.slice.call(
            track.querySelectorAll('.rs-card')
        );

        if (!cards.length) {
            return;
        }

        var index = 0;
        var autoSlide = null;
        var gap = 24;


        /**
         * Number of cards visible
         */
        function perView() {

            var width = window.innerWidth;

            if (width <= 640) {
                return 1;
            }

            if (width <= 1024) {
                return 2;
            }

            return 3;
        }


        /**
         * Build pagination dots
         */
        function buildDots() {

            dotsWrap.innerHTML = '';

            var pv = perView();
            var pages = Math.ceil(cards.length / pv);

            /*
             * No need for dots if there is only one page
             */
            if (pages <= 1) {
                return;
            }

            for (var i = 0; i < pages; i++) {

                var dot = document.createElement('button');

                dot.type = 'button';

                dot.className =
                    'rs-dot' +
                    (i === 0 ? ' is-active' : '');

                dot.setAttribute(
                    'aria-label',
                    'Go to slide ' + (i + 1)
                );


                dot.addEventListener(
                    'click',
                    (function (page) {

                        return function () {

                            var visible = perView();

                            index = Math.min(
                                page * visible,
                                Math.max(
                                    0,
                                    cards.length - visible
                                )
                            );

                            index = Math.max(
                                0,
                                index
                            );

                            render();

                            restartAutoSlide();
                        };

                    })(i)
                );


                dotsWrap.appendChild(dot);
            }
        }


        /**
         * Render slider
         */
        function render() {

            var pv = perView();

            var maxIndex = Math.max(
                0,
                cards.length - pv
            );


            /*
             * Make sure index is still valid
             * after resizing.
             */
            index = Math.min(
                index,
                maxIndex
            );

            index = Math.max(
                0,
                index
            );


            var cardWidth =
                cards[0].getBoundingClientRect().width;


            var offset =
                index * (cardWidth + gap);


            track.style.transform =
                'translate3d(-' +
                offset +
                'px, 0, 0)';


            /*
             * Navigation buttons
             */
            prevBtn.disabled = index <= 0;

            nextBtn.disabled =
                index >= maxIndex;


            /*
             * Active dot
             */
            var activePage =
                Math.floor(index / pv);


            Array.prototype.forEach.call(
                dotsWrap.children,
                function (dot, i) {

                    dot.classList.toggle(
                        'is-active',
                        i === activePage
                    );

                }
            );
        }


        /**
         * Previous
         */
        prevBtn.addEventListener(
            'click',
            function () {

                index = Math.max(
                    0,
                    index - 1
                );

                render();

                restartAutoSlide();
            }
        );


        /**
         * Next
         */
        nextBtn.addEventListener(
            'click',
            function () {

                var pv = perView();

                var maxIndex = Math.max(
                    0,
                    cards.length - pv
                );


                index = Math.min(
                    maxIndex,
                    index + 1
                );

                render();

                restartAutoSlide();
            }
        );


        /**
         * Start autoplay
         */
        function startAutoSlide() {

            /*
             * Don't autoplay if there
             * aren't enough cards.
             */
            if (cards.length <= perView()) {
                return;
            }


            stopAutoSlide();


            autoSlide = setInterval(
                function () {

                    var pv = perView();

                    var maxIndex = Math.max(
                        0,
                        cards.length - pv
                    );


                    if (index >= maxIndex) {

                        index = 0;

                    } else {

                        index++;
                    }


                    render();

                },
                5000
            );
        }


        /**
         * Stop autoplay
         */
        function stopAutoSlide() {

            if (autoSlide) {

                clearInterval(autoSlide);

                autoSlide = null;
            }
        }


        /**
         * Restart autoplay
         */
        function restartAutoSlide() {

            stopAutoSlide();

            startAutoSlide();
        }


        /**
         * Pause on hover
         */
        wrap.addEventListener(
            'mouseenter',
            stopAutoSlide
        );


        wrap.addEventListener(
            'mouseleave',
            startAutoSlide
        );


        /**
         * Resize
         */
        var resizeTimer;

        window.addEventListener(
            'resize',
            function () {

                clearTimeout(resizeTimer);


                resizeTimer = setTimeout(
                    function () {

                        index = 0;

                        buildDots();

                        render();

                        restartAutoSlide();

                    },
                    150
                );
            }
        );


        /**
         * Initial render
         */
        buildDots();

        render();

        startAutoSlide();
    }


    /**
     * Initialize all review sliders
     */
    function initAllReviewSliders() {

        var sliders =
            document.querySelectorAll('.rs-wrap');


        Array.prototype.forEach.call(
            sliders,
            function (wrap) {

                initReviewSlider(wrap);

            }
        );
    }


    /**
     * DOM ready
     */
    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initAllReviewSliders
        );

    } else {

        initAllReviewSliders();
    }

})();