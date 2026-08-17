<?php
/**
 * @var \StoreEngine\Classes\DownloadPermission[] $downloadable_permissions Order object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

?>

<?php if ( ! empty( $downloadable_permissions ) ) : ?>
	<div class="storeengine-col-lg-12">
		<div class="storeengine-summary__address">
			<h4><?php esc_html_e( 'Downloads', 'storeengine' ); ?></h4>
			<table>
				<thead>
				<tr>
					<th scope="col">Product</th>
					<th scope="col">Download</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $downloadable_permissions as $downloadable_permission ) : ?>
					<tr>
						<td><?php echo esc_html( $downloadable_permission->get_product_title() ); ?></td>
						<td>
							<a href="<?php echo esc_attr( $downloadable_permission->get_download_url() ); ?>"><?php echo esc_html( $downloadable_permission->get_file_name() ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
