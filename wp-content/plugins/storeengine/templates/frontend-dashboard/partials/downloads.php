<?php
/**
 * The Template for displaying the customer downloads list.
 *
 * This template can be overridden by copying it to yourtheme/storeengine/frontend-dashboard/partials/downloads.php.
 *
 * the readme will list any important changes.
 *
 * @version     1.0.0
 */

use StoreEngine\Utils\Helper;
use StoreEnginePro\Addons\LicenseManagement\Classes\DeploymentVersion;
use StoreEnginePro\Addons\LicenseManagement\Classes\License;
use StoreEnginePro\Addons\LicenseManagement\Classes\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited

$paged                    = max( 1, get_query_var( 'paged' ) );
$downloadable_permissions = Helper::get_download_permissions_by_customer_id( $paged );
$hasAnyItems              = [ ! empty( $downloadable_permissions ) ];
$licensedDownloads        = false;

if ( Helper::get_addon_active_status( 'license-management', true ) ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$limit             = 10;
	$offset            = absint( ( $paged - 1 ) * $limit );
	$licensedDownloads = $wpdb->get_results(
		$wpdb->prepare( "
			SELECT SQL_CALC_FOUND_ROWS v.id version_id, l.id license_id
			FROM {$wpdb->prefix}storeengine_licenses l
			JOIN {$wpdb->prefix}storeengine_deployment_versions v
			  ON v.deployment_id = l.product_id
			WHERE l.customer_id = %d
			  AND l.status = 'active'
			  AND l.product_id <> ''
			  AND v.status = 'released'
			  AND v.deployed_at = (
				SELECT MAX(v2.deployed_at)
				FROM {$wpdb->prefix}storeengine_deployment_versions v2
				WHERE v2.deployment_id = l.product_id
				  AND v2.status = 'released'
			  )
			GROUP BY l.product_id
			ORDER BY MAX(l.created_at) DESC
			LIMIT {$offset}, {$limit};",
			get_current_user_id()
		)
	);
	$found_rows        = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
	$max_num_pages     = (int) ceil( $found_rows / $limit );
	$hasAnyItems[]     = ! empty( $licensedDownloads );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

$columns = [
	'product'     => __( 'Product', 'storeengine' ),
	'version'     => __( 'Version', 'storeengine' ),
	'license_key' => __( 'License Key', 'storeengine' ),
	'actions'     => __( 'Download', 'storeengine' ),
];

// "Free version" column — only when license-management is active (the free-version
// resolver + the /se-download/free/ route live there).
if ( Helper::get_addon_active_status( 'license-management', true ) ) {
	$columns['free_version'] = __( 'Resource', 'storeengine' );
}

if ( ! function_exists( 'storeengine_render_free_version_cell' ) ) {
	/**
	 * Render the "Free version" download cell for a product row on the Downloads table.
	 *
	 * @param int    $product_id Pro product id.
	 * @param string $label      Column label (for the responsive data-label).
	 */
	function storeengine_render_free_version_cell( $product_id, $label ) {
		$free = Utils::get_free_version_info( absint( $product_id ) );
		?>
		<td class="col-free_version nobr" data-label="<?php echo esc_attr( $label ); ?>">
			<?php
			if ( $free && ! empty( $free['download_url'] ) ) {
				$fv_actions = [
					'download-free' => [
						'url'    => $free['download_url'],
						'icon'   => 'download',
						'name'   => sprintf(
						/* translators: %s: free plugin name. */
							__( 'Download %s', 'storeengine' ),
							$free['name']
						),
						'target' => '_blank',
					],
				];

				if ( ! empty( $free['guide_url'] ) ) {
					$fv_actions['setup-guide'] = [
						'url'    => $free['guide_url'],
						'icon'   => 'doc-code',
						'name'   => __( 'Setup guide', 'storeengine' ),
						'target' => '_blank',
					];
				}

				// split=false → render everything behind a "…" (kebab) dropdown.
				storeengine_render_dashboard_action_buttons( $fv_actions, 'free-version', __( 'Resource actions', 'storeengine' ), false );
			} else {
				?>
				<span class="storeengine-text-muted">&mdash;</span>
				<?php
			}
			?>
		</td>
		<?php
	}
}

?>
<div class="storeengine-dashboard__section-wrapper">
	<?php if ( ! empty( array_filter( $hasAnyItems ) ) ) { ?>
		<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--downloads">
				<thead>
				<tr>
					<?php foreach ( $columns as $column => $label ) { ?>
						<th scope="col" class="col-<?php echo esc_attr( $column ); ?> nobr">
							<?php echo esc_html( $label ); ?>
						</th>
					<?php } ?>
				</tr>
				</thead>
				<tbody>
				<?php
				// Regular downloadable products.
				if ( ! empty( $downloadable_permissions ) ) {
					foreach ( $downloadable_permissions as $downloadable_permission ) {
						?>
						<tr>
							<td class="col-product" data-label="<?php echo esc_attr( $columns['product'] ); ?>">
								<a href="<?php echo esc_url( get_permalink( $downloadable_permission->get_product_id() ) ); ?>"><?php echo esc_html( $downloadable_permission->get_product_title() ); ?></a>
							</td>
							<td class="col-version nobr" data-label="<?php echo esc_attr( $columns['version'] ); ?>">&mdash;</td>
							<td class="col-license-key nobr" data-label="<?php echo esc_attr( $columns['license_key'] ); ?>">&mdash;</td>
							<td class="col-actions nobr" data-label="<?php echo esc_attr( $columns['actions'] ); ?>">
								<a class="storeengine-btn storeengine-btn--sm storeengine-btn--download storeengine-btn--preset-blue storeengine-inline-flex storeengine-flex-align-center storeengine-flex-gap-4" href="<?php echo esc_url( $downloadable_permission->get_download_url() ); ?>">
									<?php storeengine_render_icon( 'download' ); ?>
									<span><?php esc_html_e( 'Download', 'storeengine' ); ?></span>
								</a>
							</td>
							<?php
							if ( isset( $columns['free_version'] ) ) {
								storeengine_render_free_version_cell( $downloadable_permission->get_product_id(), $columns['free_version'] );
							}
							?>
						</tr>
						<?php
					}
				}

				// Licensed / versioned downloads.
				if ( $licensedDownloads ) {
					foreach ( $licensedDownloads as $download ) {
						$license    = new License( absint( $download->license_id ) );
						$deployment = new DeploymentVersion( absint( $download->version_id ) );

						// All released versions for this product, newest first, so the
						// customer can pick a specific build to download (rollback etc.).
						$versions = Utils::get_deployments_by_product_id( $license->get_product_id() );
						if ( empty( $versions ) ) {
							$versions = [ $deployment ];
						}

						// Build a per-version option list once (file info + signed URL),
						// and capture the latest build as the default selection.
						$version_options = [];
						$default_option  = null;
						foreach ( $versions as $version ) {
							[
								'filename'     => $v_filename,
								'package_type' => $v_package_type,
								'package_size' => $v_package_size,
							] = $version->get_file_info();

							$option = [
								'id'         => $version->get_id(),
								'version'    => $version->get_version(),
								'url'        => Utils::get_secure_download_link( $version ),
								'filename'   => $v_filename,
								'filesize'   => $v_package_size,
								'type'       => $v_package_type,
								'updated_at' => $version->get_updated_at() ? human_time_diff( $version->get_updated_at()->getTimestamp() ) : '',
							];

							$version_options[] = $option;

							if ( $version->get_id() === $deployment->get_id() ) {
								$default_option = $option;
							}
						}

						if ( null === $default_option ) {
							$default_option = $version_options[0];
						}

						$title = get_the_title( $deployment->get_deployment_id() );
						?>
						<tr class="js-se-version-download">
							<td class="col-product" data-label="<?php echo esc_attr( $columns['product'] ); ?>">
								<a href="<?php echo esc_url( get_permalink( $deployment->get_deployment_id() ) ); ?>"><?php echo esc_html( $title ); ?></a>
								<div class="storeengine-sub-title-light storeengine-mt-1">
									<span class="js-se-version-type"><?php echo esc_html( $default_option['type'] ); ?></span>
								</div>
							</td>
							<td class="col-version nobr" data-label="<?php echo esc_attr( $columns['version'] ); ?>">
								<?php if ( count( $version_options ) > 1 ) { ?>
									<label>
										<span class="screen-reader-text"><?php esc_html_e( 'Choose version to download', 'storeengine' ); ?></span>
										<select class="storeengine-form-control js-se-version-select" aria-label="<?php esc_attr_e( 'Choose version to download', 'storeengine' ); ?>">
											<?php
											foreach ( $version_options as $i => $option ) {
												$is_latest = 0 === $i;
												?>
												<option
													value="<?php echo esc_attr( $option['id'] ); ?>"
													data-download-url="<?php echo esc_url( $option['url'] ); ?>"
													data-version="<?php echo esc_attr( $option['version'] ); ?>"
													data-filename="<?php echo esc_attr( $option['filename'] ); ?>"
													data-filesize="<?php echo esc_attr( $option['filesize'] ); ?>"
													data-type="<?php echo esc_attr( $option['type'] ); ?>"
													data-updated="<?php echo esc_attr( $option['updated_at'] ); ?>"
													<?php selected( $option['id'], $default_option['id'] ); ?>>
													<?php
													// translators: %s: version number.
													printf( esc_html__( 'v%s', 'storeengine' ), esc_html( $option['version'] ) );
													if ( $is_latest ) {
														echo ' ' . esc_html__( '(latest)', 'storeengine' );
													}
													?>
												</option>
												<?php
											}
											?>
										</select>
									</label>
								<?php } else { ?>
									<span class="js-se-version-label"><?php
										/* translators: %s: download version number */
										printf( esc_html__( 'v%s', 'storeengine' ), esc_html( $default_option['version'] ) );
									?></span>
								<?php } ?>
							</td>
							<td class="col-license-key nobr" data-label="<?php echo esc_attr( $columns['license_key'] ); ?>">
								<button
									type="button"
									class="copy-to-clipboard storeengine-btn storeengine-btn--sm storeengine-btn--preset-transparent storeengine-inline-flex storeengine-flex-align-center storeengine-flex-gap-4"
									data-content="<?php echo esc_attr( $license->get_license_key() ); ?>"
									data-content-name="<?php esc_attr_e( 'License Key', 'storeengine' ); ?>"
									aria-label="<?php esc_attr_e( 'Copy License Key', 'storeengine' ); ?>">
									<span class="storeengine-icon storeengine-icon--duplicate" aria-hidden="true"></span>
									<span><?php esc_html_e( 'Copy', 'storeengine' ); ?></span>
								</button>
							</td>
							<td class="col-actions nobr" data-label="<?php echo esc_attr( $columns['actions'] ); ?>">
								<a class="storeengine-btn storeengine-btn--sm storeengine-btn--download storeengine-btn--preset-blue js-se-version-download-btn storeengine-inline-flex storeengine-flex-align-center storeengine-flex-gap-4" href="<?php echo esc_url( $default_option['url'] ); ?>">
									<?php storeengine_render_icon( 'download' ); ?>
									<span><?php esc_html_e( 'Download', 'storeengine' ); ?></span>
								</a>
							</td>
							<?php
							if ( isset( $columns['free_version'] ) ) {
								storeengine_render_free_version_cell( $license->get_product_id(), $columns['free_version'] );
							}
							?>
						</tr>
						<?php
					}
				}
				?>
				</tbody>
			</table>
		</div>
		<?php do_action( 'storeengine/templates/dashboard_downloads_pagination', $downloadable_permissions ); ?>
	<?php } else { ?>
		<?php
		storeengine_oops_message( [
			'classes' => 'storeengine-my-5',
			'title'   => __( 'No Downloads Found!', 'storeengine' ),
			'message' => __( 'No downloads available. You haven’t purchased any downloadable products yet.', 'storeengine' ),
		] );
		?>
	<?php } ?>
</div>
