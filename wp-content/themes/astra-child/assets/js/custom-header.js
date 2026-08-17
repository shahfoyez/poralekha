document.addEventListener('DOMContentLoaded', function () {

    const header = document.querySelector('.pl-header');

    if (!header) {
        return;
    }


    /* =====================================================
       MOBILE MENU
    ====================================================== */

    const mobileToggle = header.querySelector(
        '.pl-header__mobile-toggle'
    );

    const navigation = header.querySelector(
        '.pl-header__nav'
    );


    if (mobileToggle && navigation) {

        mobileToggle.addEventListener('click', function () {

            const isOpen =
                mobileToggle.classList.toggle('is-open');

            navigation.classList.toggle(
                'is-open',
                isOpen
            );

            mobileToggle.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );

        });

    }


    /* =====================================================
       MOBILE COURSE DROPDOWN
    ====================================================== */

    const courseMenu = header.querySelector(
        '.pl-menu-item--has-dropdown'
    );

    if (courseMenu) {

        const courseLink = courseMenu.querySelector(
            '.pl-menu-link'
        );

        if (courseLink) {

            courseLink.addEventListener('click', function (event) {

                /*
                 * Only intercept on mobile.
                 */
                if (window.innerWidth <= 900) {

                    const isOpen =
                        courseMenu.classList.contains('is-open');

                    if (!isOpen) {

                        event.preventDefault();

                        courseMenu.classList.add(
                            'is-open'
                        );

                    }

                }

            });

        }

    }


    /* =====================================================
       USER DROPDOWN
    ====================================================== */

    const userMenu = header.querySelector(
        '.pl-menu-item--user'
    );

    const userButton = header.querySelector(
        '.pl-user-button'
    );


    if (userMenu && userButton) {

        userButton.addEventListener('click', function (event) {

            event.preventDefault();

            const isOpen =
                userMenu.classList.toggle('is-open');

            userButton.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );

        });

    }


    /* =====================================================
       CLICK OUTSIDE
    ====================================================== */

    document.addEventListener('click', function (event) {

        if (!header.contains(event.target)) {

            if (navigation) {

                navigation.classList.remove(
                    'is-open'
                );

            }

            if (mobileToggle) {

                mobileToggle.classList.remove(
                    'is-open'
                );

                mobileToggle.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

            if (courseMenu) {

                courseMenu.classList.remove(
                    'is-open'
                );

            }

            if (userMenu) {

                userMenu.classList.remove(
                    'is-open'
                );

            }

        }

    });


    /* =====================================================
       ESCAPE
    ====================================================== */

    document.addEventListener('keydown', function (event) {

        if (event.key !== 'Escape') {
            return;
        }

        if (navigation) {
            navigation.classList.remove('is-open');
        }

        if (mobileToggle) {

            mobileToggle.classList.remove(
                'is-open'
            );

            mobileToggle.setAttribute(
                'aria-expanded',
                'false'
            );

        }

        if (courseMenu) {
            courseMenu.classList.remove('is-open');
        }

        if (userMenu) {
            userMenu.classList.remove('is-open');
        }

    });

});