<?php
/**
 * Reviews Shortcode
 *
 * Usage:
 *
 * [site_reviews]
 *
 * Optional:
 *
 * [site_reviews limit="10"]
 */
function medlearn_reviews_shortcode($atts) {

    $atts = shortcode_atts(
            array(
                    'limit' => -1,
            ),
            $atts,
            'site_reviews'
    );


    $reviews = new WP_Query(
            array(
                    'post_type'      => 'site_review',
                    'post_status'    => 'publish',
                    'posts_per_page' => intval($atts['limit']),
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'no_found_rows'  => true,
            )
    );


    if (!$reviews->have_posts()) {
        return '';
    }


    ob_start();

    ?>

    <div class="rs-wrap">

        <button
                class="rs-nav rs-nav-prev"
                aria-label="Previous"
                type="button"
        >
            <svg viewBox="0 0 24 24">
                <path d="M15 5l-7 7 7 7"/>
            </svg>
        </button>


        <div class="rs-viewport">

            <div class="rs-track">

                <?php while ($reviews->have_posts()) : $reviews->the_post(); ?>

                    <?php

                    $review_id = get_the_ID();

                    $name = get_post_meta(
                            $review_id,
                            '_review_name',
                            true
                    );

                    $designation = get_post_meta(
                            $review_id,
                            '_review_designation',
                            true
                    );

                    $image_id = get_post_meta(
                            $review_id,
                            '_review_image',
                            true
                    );

                    $review_content = get_post_meta(
                            $review_id,
                            '_review_content',
                            true
                    );

                    $rating = (int) get_post_meta(
                            $review_id,
                            '_review_number',
                            true
                    );

                    $rating = min(
                            5,
                            max(1, $rating)
                    );

                    ?>


                    <div class="rs-card">

                        <div class="rs-top">

                            <div class="rs-stars">

                                <?php for ($i = 1; $i <= 5; $i++) : ?>

                                    <svg
                                            class="rs-star <?php echo $i > $rating ? 'is-empty' : ''; ?>"
                                            viewBox="0 0 20 20"
                                            aria-hidden="true"
                                    >
                                        <path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9L1.5 7.7l5.9-.8L10 1.5z"/>
                                    </svg>

                                <?php endfor; ?>

                            </div>


                            <svg
                                    class="rs-quote"
                                    viewBox="0 0 44 24"
                                    aria-hidden="true"
                            >
                                <path d="M0 24V13.5C0 6 4.5 1 12 0v5.5C8 6.5 6 9 6 13h6v11H0zM22 24V13.5C22 6 26.5 1 34 0v5.5c-4 1-6 3.5-6 7.5h6v11H22z"/>
                            </svg>

                        </div>


                        <div class="rs-text">

                            <?php
                            echo wp_kses_post(
                                    wpautop($review_content)
                            );
                            ?>

                        </div>


                        <div class="rs-person">

                            <?php if ($image_id) : ?>

                                <?php

                                echo wp_get_attachment_image(
                                        $image_id,
                                        'thumbnail',
                                        false,
                                        array(
                                                'class' => 'rs-avatar',
                                                'alt'   => $name,
                                        )
                                );

                                ?>

                            <?php endif; ?>


                            <div>

                                <?php if ($name) : ?>

                                    <p class="rs-name">
                                        <?php echo esc_html($name); ?>
                                    </p>

                                <?php endif; ?>


                                <?php if ($designation) : ?>

                                    <p class="rs-role">
                                        <?php echo esc_html($designation); ?>
                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            </div>

        </div>


        <button
                class="rs-nav rs-nav-next"
                aria-label="Next"
                type="button"
        >
            <svg viewBox="0 0 24 24">
                <path d="M9 5l7 7-7 7"/>
            </svg>
        </button>


        <div class="rs-dots"></div>

    </div>

    <?php

    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode(
        'site_reviews',
        'medlearn_reviews_shortcode'
);

/**
 * Academy LMS Course Categories Shortcode
 *
 * Usage:
 * [academy_course_categories]
 */
function academy_course_categories_shortcode($atts) {

    $atts = shortcode_atts(
        array(
            'taxonomy' => 'academy_courses_category',
            'limit'    => 6,
        ),
        $atts,
        'academy_course_categories'
    );

    $course_categories = get_terms(
        array(
            'taxonomy'   => $atts['taxonomy'],
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => absint($atts['limit']),
        )
    );

    if (is_wp_error($course_categories) || empty($course_categories)) {
        return '';
    }

    ob_start();
    ?>

    <div class="academy-course-categories">

        <?php foreach ($course_categories as $index => $category) :

            $category_link = get_term_link($category);

            if (is_wp_error($category_link)) {
                continue;
            }

            $description = term_description($category->term_id, $atts['taxonomy']);

            /*
             * Icon classes:
             * 0 = Learning
             * 1 = Instructor
             * 2 = Courses
             * 3 = Support
             * 4 = Pricing
             * 5 = Access
             */
            $icon_type = $index % 6;

            ?>

            <a
                href="<?php echo esc_url($category_link); ?>"
                class="academy-course-category"
            >

                <div class="academy-course-category__icon academy-course-category__icon--<?php echo esc_attr($icon_type); ?>">

                    <?php
                    switch ($icon_type) :

                        // Flexible Learning
                        case 0:
                            ?>
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <path d="M24 7L31 11V19L24 23L17 19V11L24 7Z"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <circle cx="24" cy="15" r="3"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <path d="M17 19L10 23V31L17 35L24 31V23"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M31 19L38 23V31L31 35L24 31"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                            </svg>
                            <?php
                            break;

                        // Expert Instructor
                        case 1:
                            ?>
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <rect x="9" y="8" width="30" height="23" rx="2"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <circle cx="18" cy="18" r="4"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <path d="M12 27C13.5 23.5 16 22 19 22C22 22 24.5 23.5 26 27"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M29 15H35M29 20H35"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M24 31V37M17 37H31"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                            </svg>
                            <?php
                            break;

                        // Accredited Courses
                        case 2:
                            ?>
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <path d="M7 10C12 8 17 9 24 13V39C17 35 12 34 7 36V10Z"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M41 10C36 8 31 9 24 13V39C31 35 36 34 41 36V10Z"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M24 13V39"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                            </svg>
                            <?php
                            break;

                        // Career Support
                        case 3:
                            ?>
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <path d="M10 25V21C10 13.8 15.8 8 23 8H25C32.2 8 38 13.8 38 21V25"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M10 23H8C6.9 23 6 23.9 6 25V30C6 31.1 6.9 32 8 32H12V23"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M38 23H40C41.1 23 42 23.9 42 25V30C42 31.1 41.1 32 40 32H36V23"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M36 32C35 36 31 38 26 38H23"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <circle cx="22" cy="38" r="2"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                            </svg>
                            <?php
                            break;

                        // Affordable Pricing
                        case 4:
                            ?>
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <path d="M24 7L28 10L33 9L36 13L41 15L40 20L43 24L40 28L41 33L36 35L33 39L28 38L24 41L20 38L15 39L12 35L7 33L8 28L5 24L8 20L7 15L12 13L15 9L20 10L24 7Z"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <circle cx="19" cy="20" r="2"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <circle cx="29" cy="28" r="2"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <path d="M30 18L18 30"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                            </svg>
                            <?php
                            break;

                        // Lifetime Access
                        case 5:
                            ?>
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <rect x="13" y="21" width="22" height="20" rx="3"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <path d="M18 21V15C18 11.7 20.7 9 24 9C27.3 9 30 11.7 30 15V21"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                                <circle cx="24" cy="30" r="2"
                                        stroke="currentColor"
                                        stroke-width="2"/>
                                <path d="M24 32V36"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                            </svg>
                            <?php
                            break;

                    endswitch;
                    ?>

                </div>

                <h3 class="academy-course-category__title">
                    <?php echo esc_html($category->name); ?>
                </h3>

                <?php if (!empty($description)) : ?>
                    <div class="academy-course-category__description">
                        <?php echo wp_kses_post(wp_strip_all_tags($description)); ?>
                    </div>
                <?php endif; ?>

            </a>

        <?php endforeach; ?>

    </div>

    <?php
    return ob_get_clean();
}

add_shortcode(
    'academy_course_categories',
    'academy_course_categories_shortcode'
);