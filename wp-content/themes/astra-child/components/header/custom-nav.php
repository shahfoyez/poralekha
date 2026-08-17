<?php
/**
 * Custom Poralekha Header
 *
 * @package Poralekha
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name = get_bloginfo( 'name' );

/*
 * Get the logo.
 *
 * If you have configured:
 * Appearance > Customize > Site Identity > Logo
 * Astra/WordPress will return it here.
 */
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_url       = $custom_logo_id
    ? wp_get_attachment_image_url( $custom_logo_id, 'full' )
    : '';

/*
 * Academy course taxonomy.
 *
 * Change this if your Academy installation uses a different
 * taxonomy slug.
 */
$course_taxonomy = 'academy_courses_category';

$course_categories = get_terms(
    array(
        'taxonomy'   => $course_taxonomy,
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    )
);

/*
 * Current user information.
 */
$is_logged_in = is_user_logged_in();
$current_user = wp_get_current_user();

$user_initials = '';

if ( $is_logged_in && $current_user->exists() ) {

    $first_name = trim( $current_user->first_name );
    $last_name  = trim( $current_user->last_name );

    if ( $first_name || $last_name ) {

        $user_initials =
            strtoupper(
                substr( $first_name, 0, 1 ) .
                substr( $last_name, 0, 1 )
            );

    } else {

        $display_name = trim( $current_user->display_name );

        $parts = preg_split(
            '/\s+/',
            $display_name
        );

        $user_initials = strtoupper(
            substr( $parts[0], 0, 1 ) .
            ( isset( $parts[1] ) ? substr( $parts[1], 0, 1 ) : '' )
        );
    }

    /*
     * Fallback if we somehow still don't have initials.
     */
    if ( ! $user_initials ) {
        $user_initials = strtoupper(
            substr( $current_user->user_login, 0, 1 )
        );
    }
}

$login_url = wp_login_url(
    home_url( '/' )
);

$logout_url = wp_logout_url(
    home_url( '/' )
);

$dashboard_url = function_exists( 'academy_get_dashboard_url' )
    ? academy_get_dashboard_url()
    : home_url( '/dashboard/' );

$profile_url = home_url().'/dashboard/settings/';
?>

<header class="pl-header">

    <div class="pl-header__container">

        <!-- =====================================================
             LEFT
        ====================================================== -->

        <div class="pl-header__left">

            <a
                href="<?php echo esc_url( home_url( '/' ) ); ?>"
                class="pl-header__logo"
                aria-label="<?php echo esc_attr( $site_name ); ?>"
            >

                <?php if ( $logo_url ) : ?>

                    <img
                        src="<?php echo esc_url( $logo_url ); ?>"
                        alt="<?php echo esc_attr( $site_name ); ?>"
                    >

                <?php else : ?>

                    <span class="pl-header__logo-text">
                        <?php echo esc_html( $site_name ); ?>
                    </span>

                <?php endif; ?>

            </a>


            <!-- SEARCH -->

            <form
                class="pl-header__search"
                method="get"
                action="<?php echo esc_url( home_url( '/' ) ); ?>"
            >

                <span class="pl-header__search-icon">
                    <svg
                        width="19"
                        height="19"
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                    >
                        <circle
                            cx="11"
                            cy="11"
                            r="7"
                            stroke="currentColor"
                            stroke-width="2"
                        />

                        <path
                            d="M16.5 16.5L21 21"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        />
                    </svg>
                </span>

                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr( get_search_query() ); ?>"
                    placeholder="Search courses..."
                    autocomplete="off"
                >

                <input
                    type="hidden"
                    name="post_type"
                    value="academy_courses"
                >

            </form>

        </div>


        <!-- =====================================================
             MOBILE TOGGLE
        ====================================================== -->

        <button
            class="pl-header__mobile-toggle"
            type="button"
            aria-label="Open navigation"
            aria-expanded="false"
            aria-controls="pl-primary-navigation"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>


        <!-- =====================================================
             RIGHT / NAVIGATION
        ====================================================== -->

        <nav
            id="pl-primary-navigation"
            class="pl-header__nav"
            aria-label="Primary navigation"
        >

            <ul class="pl-header__menu">

                <!-- ALL COURSES -->

                <li class="pl-menu-item pl-menu-item--has-dropdown">

                    <a
                        href="#"
                        class="pl-menu-link"
                    >

                        <span>All Courses</span>

                        <svg
                            class="pl-menu-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <path
                                d="M6 9L12 15L18 9"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>

                    </a>


                    <?php if ( ! empty( $course_categories ) && ! is_wp_error( $course_categories ) ) : ?>

                        <div class="pl-dropdown">

                            <div class="pl-dropdown__inner">

                                <div class="pl-dropdown__header">
                                    <span>Explore Courses</span>
                                    <small>
                                        Find the right course for you
                                    </small>
                                </div>


                                <div class="pl-dropdown__categories">

                                    <?php foreach ( $course_categories as $category ) : ?>

                                        <a
                                            href="<?php echo esc_url( get_term_link( $category ) ); ?>"
                                            class="pl-category-link"
                                        >

                                            <span class="pl-category-icon">

                                                <?php
                                                echo esc_html(
                                                    strtoupper(
                                                        substr(
                                                            $category->name,
                                                            0,
                                                            1
                                                        )
                                                    )
                                                );
                                                ?>

                                            </span>

                                            <span class="pl-category-name">
                                                <?php echo esc_html( $category->name ); ?>
                                            </span>

                                            <span class="pl-category-arrow">
                                                →
                                            </span>

                                        </a>

                                    <?php endforeach; ?>

                                </div>


                                <!-- ALL COURSES -->

                                <a
                                    href="<?php echo esc_url( home_url( '/courses/' ) ); ?>"
                                    class="pl-dropdown__all-courses"
                                >

                                    <span>
                                        View All Courses
                                    </span>

                                    <span>
                                        →
                                    </span>

                                </a>

                            </div>

                        </div>

                    <?php endif; ?>

                </li>


                <!-- ABOUT -->

                <li class="pl-menu-item">

                    <a
                        href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"
                        class="pl-menu-link"
                    >
                        About Us
                    </a>

                </li>


                <!-- CONTACT -->

                <li class="pl-menu-item">

                    <a
                        href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"
                        class="pl-menu-link"
                    >
                        Contact Us
                    </a>

                </li>


                <!-- USER -->

                <li class="pl-menu-item pl-menu-item--user">

                    <?php if ( $is_logged_in ) : ?>

                        <button
                            type="button"
                            class="pl-user-button"
                            aria-expanded="false"
                        >

                            <span class="pl-user-avatar">
                                <?php echo esc_html( $user_initials ); ?>
                            </span>

                            <span class="pl-user-name">
                                <?php
                                echo esc_html(
                                    $current_user->display_name
                                );
                                ?>
                            </span>

                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M6 9L12 15L18 9"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>

                        </button>


                        <div class="pl-user-dropdown">
                            <div class="pl-user-dropdown__inner">
                                <div class="pl-user-dropdown__header">

                                <span class="pl-user-dropdown__avatar">
                                    <?php echo esc_html( $user_initials ); ?>
                                </span>

                                    <div>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                    $current_user->display_name
                                            );
                                            ?>
                                        </strong>

                                        <small>
                                            <?php
                                            echo esc_html(
                                                    $current_user->user_email
                                            );
                                            ?>
                                        </small>

                                    </div>

                                </div>


                                <div class="pl-user-dropdown__links">

                                    <a href="<?php echo esc_url( $profile_url ); ?>">
                                        Edit Profile
                                    </a>

                                    <a href="<?php echo esc_url( $dashboard_url ); ?>">
                                        Dashboard
                                    </a>

                                </div>


                                <a
                                        href="<?php echo esc_url( $logout_url ); ?>"
                                        class="pl-user-dropdown__logout"
                                >
                                    Log Out
                                </a>
                            </div>
                        </div>

                    <?php else : ?>

                        <a
                            href="<?php echo esc_url( $login_url ); ?>"
                            class="pl-login-button"
                        >
                            Login / Sign Up
                        </a>

                    <?php endif; ?>

                </li>

            </ul>

        </nav>

    </div>

</header>