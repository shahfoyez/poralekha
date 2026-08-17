<?php

$course_id = $args['course_id'] ?? 0;

if (!$course_id) {
    return;
}

$course = get_post($course_id);

if (!$course) {
    return;
}

$title     = get_the_title($course_id);
$thumbnail = get_the_post_thumbnail_url($course_id, 'medium_large');
?>

<article class="pl-course-card">

    <a
        href="<?php echo esc_url(get_permalink($course_id)); ?>"
        class="pl-course-card__image"
    >
        <?php if ($thumbnail) : ?>

            <img
                src="<?php echo esc_url($thumbnail); ?>"
                alt="<?php echo esc_attr($title); ?>"
            >

        <?php endif; ?>
    </a>

    <div class="pl-course-card__content">

        <h3 class="pl-course-card__title">
            <a href="<?php echo esc_url(get_permalink($course_id)); ?>">
                <?php echo esc_html($title); ?>
            </a>
        </h3>

        <div class="pl-course-card__meta">

            <!-- category -->

            <!-- rating -->

            <!-- duration -->

        </div>

        <div class="pl-course-card__footer">

            <!-- price -->

            <a
                href="<?php echo esc_url(get_permalink($course_id)); ?>"
                class="pl-course-card__button"
            >
                View Course
            </a>

        </div>

    </div>

</article>