<?php
/**
 * Review Admin Scripts
 */
/**
 * Load WordPress Media Library on Review edit screens
 */
function poralekha_review_admin_media() {

    global $post_type;

    if ($post_type !== 'site_review') {
        return;
    }

    wp_enqueue_media();
}

add_action(
    'admin_enqueue_scripts',
    'poralekha_review_admin_media'
);


/**
 * Review Media Picker JS
 */
function poralekha_review_media_picker_script() {

    global $post_type, $pagenow;

    if (
        $post_type !== 'site_review' ||
        !in_array(
            $pagenow,
            array('post.php', 'post-new.php'),
            true
        )
    ) {
        return;
    }

    ?>

    <script>
        jQuery(document).ready(function($) {

            let reviewImageFrame;


            /*
             * Open Media Library
             */
            $(document).on('click', '#review_image_button', function(e) {

                e.preventDefault();

                if (reviewImageFrame) {
                    reviewImageFrame.open();
                    return;
                }


                reviewImageFrame = wp.media({
                    title: 'Select Review Image',

                    button: {
                        text: 'Use This Image'
                    },

                    multiple: false,

                    library: {
                        type: 'image'
                    }
                });


                reviewImageFrame.on('select', function() {

                    const attachment = reviewImageFrame
                        .state()
                        .get('selection')
                        .first()
                        .toJSON();


                    /*
                     * Save attachment ID
                     */
                    $('#review_image').val(attachment.id);


                    /*
                     * Get image URL
                     */
                    let imageUrl = attachment.url;

                    if (
                        attachment.sizes &&
                        attachment.sizes.thumbnail
                    ) {
                        imageUrl = attachment.sizes.thumbnail.url;
                    }


                    /*
                     * Show preview
                     */
                    $('#review_image_preview').html(
                        '<img src="' +
                        imageUrl +
                        '" ' +
                        'style="max-width:120px;height:auto;display:block;" />'
                    );


                    /*
                     * Show remove button
                     */
                    $('#review_image_remove').show();

                });


                reviewImageFrame.open();

            });


            /*
             * Remove Image
             */
            $(document).on('click', '#review_image_remove', function(e) {

                e.preventDefault();


                $('#review_image').val('');

                $('#review_image_preview').html('');

                $(this).hide();

            });

        });
    </script>

    <?php
}

add_action(
    'admin_footer',
    'poralekha_review_media_picker_script'
);