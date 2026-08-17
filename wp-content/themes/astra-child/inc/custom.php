<?php
/**
 * Register Reviews Post Type
 */
function poralekha_register_reviews_post_type() {

    register_post_type('site_review', array(
            'labels' => array(
                    'name'               => 'Reviews',
                    'singular_name'      => 'Review',
                    'add_new'            => 'Add New',
                    'add_new_item'       => 'Add New Review',
                    'edit_item'          => 'Edit Review',
                    'new_item'           => 'New Review',
                    'view_item'          => 'View Review',
                    'search_items'       => 'Search Reviews',
                    'not_found'          => 'No reviews found',
                    'menu_name'          => 'Reviews',
            ),

            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-star-filled',

            'supports' => array(
                    'title',
            ),

            'show_in_rest'       => false,
    ));
}

add_action(
        'init',
        'poralekha_register_reviews_post_type'
);
/**
 * Add Review Meta Box
 */
function poralekha_add_review_meta_box() {

    add_meta_box(
            'poralekha_review_details',
            'Review Details',
            'poralekha_render_review_meta_box',
            'site_review',
            'normal',
            'high'
    );
}

add_action(
        'add_meta_boxes',
        'poralekha_add_review_meta_box'
);


/**
 * Render Review Meta Box
 */
function poralekha_render_review_meta_box($post) {

    wp_nonce_field(
            'poralekha_save_review',
            'poralekha_review_nonce'
    );

    $name = get_post_meta(
            $post->ID,
            '_review_name',
            true
    );

    $designation = get_post_meta(
            $post->ID,
            '_review_designation',
            true
    );

    $image_id = get_post_meta(
            $post->ID,
            '_review_image',
            true
    );

    $review_content = get_post_meta(
            $post->ID,
            '_review_content',
            true
    );

    $review_number = get_post_meta(
            $post->ID,
            '_review_number',
            true
    );

    ?>

    <div class="poralekha-review-fields">

        <p>
            <label for="review_name">
                <strong>Name</strong>
            </label>

            <input
                    type="text"
                    id="review_name"
                    name="review_name"
                    value="<?php echo esc_attr($name); ?>"
                    class="widefat"
            >
        </p>

        <p>
            <label for="review_designation">
                <strong>Designation</strong>
            </label>

            <input
                    type="text"
                    id="review_designation"
                    name="review_designation"
                    value="<?php echo esc_attr($designation); ?>"
                    class="widefat"
            >
        </p>

        <p>
            <label>
                <strong>Review Image</strong>
            </label>
        </p>

        <div class="review-image-wrapper">

            <input
                    type="hidden"
                    id="review_image"
                    name="review_image"
                    value="<?php echo esc_attr($image_id); ?>"
            >

            <div
                    id="review_image_preview"
                    style="margin-bottom:10px;"
            >

                <?php

                if ($image_id) {

                    echo wp_get_attachment_image(
                            $image_id,
                            'thumbnail',
                            false,
                            array(
                                    'style' => 'max-width:120px;height:auto;display:block;',
                            )
                    );

                }

                ?>

            </div>


            <button
                    type="button"
                    class="button"
                    id="review_image_button"
            >
                Select Image
            </button>


            <button
                    type="button"
                    class="button"
                    id="review_image_remove"
                    <?php echo !$image_id ? 'style="display:none;"' : ''; ?>
            >
                Remove Image
            </button>

        </div>

        <p>
            <label for="review_number">
                <strong>Review Number / Rating</strong>
            </label>

            <select
                    id="review_number"
                    name="review_number"
                    class="widefat"
            >

                <?php for ($i = 1; $i <= 5; $i++) : ?>

                    <option
                            value="<?php echo esc_attr($i); ?>"
                            <?php selected($review_number, $i); ?>
                    >
                        <?php echo esc_html($i); ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                    </option>

                <?php endfor; ?>

            </select>
        </p>

        <p>
            <label>
                <strong>Review Content</strong>
            </label>
        </p>

        <?php

        wp_editor(
                $review_content,
                'review_content',
                array(
                        'textarea_name' => 'review_content',
                        'textarea_rows' => 6,
                        'media_buttons' => false,
                        'teeny'         => true,
                )
        );

        ?>

    </div>

    <?php
}
/**
 * Save Review Meta
 */
function poralekha_save_review_meta($post_id) {

    if (
            !isset($_POST['poralekha_review_nonce']) ||
            !wp_verify_nonce(
                    $_POST['poralekha_review_nonce'],
                    'poralekha_save_review'
            )
    ) {
        return;
    }

    if (
            defined('DOING_AUTOSAVE') &&
            DOING_AUTOSAVE
    ) {
        return;
    }

    if (
            !current_user_can(
                    'edit_post',
                    $post_id
            )
    ) {
        return;
    }

    if (
            get_post_type($post_id) !== 'site_review'
    ) {
        return;
    }


    /*
     * Name
     */
    if (isset($_POST['review_name'])) {

        update_post_meta(
                $post_id,
                '_review_name',
                sanitize_text_field($_POST['review_name'])
        );
    }


    /*
     * Designation
     */
    if (isset($_POST['review_designation'])) {

        update_post_meta(
                $post_id,
                '_review_designation',
                sanitize_text_field($_POST['review_designation'])
        );
    }


    /*
     * Image
     */
    if (isset($_POST['review_image'])) {

        update_post_meta(
                $post_id,
                '_review_image',
                absint($_POST['review_image'])
        );
    }


    /*
     * Review Content
     */
    if (isset($_POST['review_content'])) {

        update_post_meta(
                $post_id,
                '_review_content',
                wp_kses_post($_POST['review_content'])
        );
    }


    /*
     * Rating
     */
    if (isset($_POST['review_number'])) {

        $rating = absint($_POST['review_number']);

        $rating = min(
                5,
                max(1, $rating)
        );

        update_post_meta(
                $post_id,
                '_review_number',
                $rating
        );
    }
}

add_action(
        'save_post_site_review',
        'poralekha_save_review_meta'
);