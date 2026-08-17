<?php

defined( 'ABSPATH' ) || exit;

/**
 * No Content message template for table.
 * If column size is defined and table layout set to fixed, UI will be broken.
 *
 * @version 1.0.0
 * @since 1.8.0
 */
$storeengine_args = $args ?? [
	'image'   => $image ?? null,
	'title'   => $title ?? null,
	'message' => $message ?? null,
	'classes' => $classes ?? null
];
?>
<tr>
	<td colspan="<?php echo esc_attr( $columns ?? 100 )?>" class="oops-message">
		<?php storeengine_oops_message( $storeengine_args ); ?>
	</td>
</tr>
<?php
