<?php
/**
 * Customer dashboard — Reviews.
 *
 * Lists products the customer bought, showing their review (stars, media,
 * status) with an actions menu to view or edit it inline.
 *
 * @var array $items List of { product, reviewed, comment }.
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$reviews_enabled = (bool) Helper::get_settings( 'enable_product_reviews', true );
$media_max       = storeengine_review_media_max();
$media_max_size  = storeengine_review_media_max_size();
?>
<div class="storeengine-frontend-dashboard--reviews">
	<div class="storeengine-dashboard__section-title">
		<h4 class="storeengine-dashboard__section-title-heading"><?php esc_html_e( 'My Reviews', 'storeengine' ); ?></h4>
	</div>

	<?php if ( ! $reviews_enabled ) : ?>
		<p class="storeengine-dashboard__empty"><?php esc_html_e( 'Product reviews are currently disabled.', 'storeengine' ); ?></p>
	<?php elseif ( empty( $items ) ) : ?>
		<p class="storeengine-dashboard__empty"><?php esc_html_e( 'You can review products after your order is completed.', 'storeengine' ); ?></p>
	<?php else : ?>
		<div class="storeengine-dashboard__section storeengine-dashboard__table-wrapper">
			<table class="storeengine-dashboard__table storeengine-dashboard__table--reviews">
				<thead>
				<tr>
					<th scope="col" class="col-product"><?php esc_html_e( 'Product', 'storeengine' ); ?></th>
					<th scope="col" class="col-review"><?php esc_html_e( 'Your Review', 'storeengine' ); ?></th>
					<th scope="col" class="col-status"><?php esc_html_e( 'Status', 'storeengine' ); ?></th>
					<th scope="col" class="col-actions"><?php esc_html_e( 'Actions', 'storeengine' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $items as $item ) :
					$product     = $item['product'];
					$product_id  = $product->get_id();
					$permalink   = $product->get_permalink();
					$review_link = $permalink . '#storeengine-reviews';
					$comment     = $item['comment'];
					$rating      = $comment ? (int) get_comment_meta( $comment->comment_ID, 'storeengine_rating', true ) : 0;
					$media       = $comment ? storeengine_get_review_media( $comment->comment_ID ) : [];
					$is_pending  = $comment && '0' === (string) $comment->comment_approved;
					?>
					<tr class="storeengine-dashboard-review__row">
						<td class="col-product" data-title="<?php esc_attr_e( 'Product', 'storeengine' ); ?>">
							<div class="storeengine-dashboard-review__product">
								<?php
								$thumb = get_the_post_thumbnail( $product_id, 'thumbnail', [ 'class' => 'storeengine-dashboard-review__thumb' ] );
								if ( $thumb ) {
									echo wp_kses_post( $thumb );
								}
								?>
								<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
							</div>
						</td>
						<td class="col-review" data-title="<?php esc_attr_e( 'Your Review', 'storeengine' ); ?>">
							<?php if ( $item['reviewed'] && $comment ) : ?>
								<div class="storeengine-dashboard-review__rating"><?php echo wp_kses_post( Helper::star_rating_generator( $rating ) ); ?></div>
								<?php if ( '' !== trim( $comment->comment_content ) ) : ?>
									<div class="storeengine-dashboard-review__text"><?php echo esc_html( wp_trim_words( $comment->comment_content, 24 ) ); ?></div>
								<?php endif; ?>
								<?php if ( ! empty( $media ) ) : ?>
									<div class="storeengine-dashboard-review__media">
										<?php foreach ( $media as $m ) : ?>
											<a class="storeengine-dashboard-review__media-item is-<?php echo esc_attr( $m['type'] ); ?>" href="<?php echo esc_url( $m['url'] ); ?>" target="_blank" rel="noopener">
												<?php if ( 'video' === $m['type'] ) : ?>
													<video src="<?php echo esc_url( $m['url'] ); ?>" muted></video>
												<?php else : ?>
													<img src="<?php echo esc_url( $m['thumb'] ); ?>" alt="" />
												<?php endif; ?>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php else : ?>
								<span class="storeengine-dashboard-review__none"><?php esc_html_e( 'Not reviewed yet', 'storeengine' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="col-status" data-title="<?php esc_attr_e( 'Status', 'storeengine' ); ?>">
							<?php if ( $item['reviewed'] && $comment ) : ?>
								<span class="storeengine-dashboard-review__status <?php echo $is_pending ? 'is-pending' : 'is-approved'; ?>">
									<?php echo $is_pending ? esc_html__( 'Pending', 'storeengine' ) : esc_html__( 'Approved', 'storeengine' ); ?>
								</span>
							<?php else : ?>
								<span class="storeengine-dashboard-review__status is-none">—</span>
							<?php endif; ?>
						</td>
						<td class="col-actions" data-title="<?php esc_attr_e( 'Actions', 'storeengine' ); ?>">
							<?php if ( $item['reviewed'] && $comment ) : ?>
								<div class="storeengine-dashboard-review__menu">
									<button type="button" class="storeengine-dashboard-review__menu-toggle" aria-label="<?php esc_attr_e( 'Actions', 'storeengine' ); ?>" aria-haspopup="true" aria-expanded="false">
										<span class="storeengine-icon storeengine-icon--three-dots-menu" aria-hidden="true"></span>
									</button>
									<div class="storeengine-dashboard-review__menu-list" role="menu">
										<a href="<?php echo esc_url( $review_link ); ?>" role="menuitem">
											<span class="storeengine-icon storeengine-icon--eye" aria-hidden="true"></span>
											<?php esc_html_e( 'View', 'storeengine' ); ?>
										</a>
										<button type="button" class="storeengine-dashboard-review__edit-btn" role="menuitem">
											<span class="storeengine-icon storeengine-icon--edit" aria-hidden="true"></span>
											<?php esc_html_e( 'Edit', 'storeengine' ); ?>
										</button>
									</div>
								</div>
							<?php else : ?>
								<button type="button" class="storeengine-btn storeengine-btn--sm storeengine-btn--bg-blue storeengine-dashboard-review__edit-btn"><?php esc_html_e( 'Write a review', 'storeengine' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>

					<?php
					$storeengine_is_edit = $item['reviewed'] && $comment;
					?>
						<tr class="storeengine-dashboard-review__edit-row"<?php echo $storeengine_is_edit ? ' data-comment-id="' . esc_attr( $comment->comment_ID ) . '"' : ''; ?> hidden>
							<td colspan="4">
								<div class="storeengine-dashboard-review-edit" data-mode="<?php echo $storeengine_is_edit ? 'edit' : 'add'; ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>">
									<label class="storeengine-dashboard-review-edit__label"><?php esc_html_e( 'Your rating', 'storeengine' ); ?></label>
									<div class="storeengine-dashboard-review-edit__stars" data-rating="<?php echo esc_attr( $rating ); ?>">
										<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
											<button type="button" class="storeengine-dashboard-review-edit__star<?php echo $i <= $rating ? ' is-on' : ''; ?>" data-value="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( $i ); ?>">&#9733;</button>
										<?php endfor; ?>
									</div>

									<label class="storeengine-dashboard-review-edit__label"><?php esc_html_e( 'Your review', 'storeengine' ); ?></label>
									<textarea class="storeengine-dashboard-review-edit__text" rows="4" placeholder="<?php esc_attr_e( 'Share your thoughts about this product…', 'storeengine' ); ?>"><?php echo $comment ? esc_textarea( $comment->comment_content ) : ''; ?></textarea>

									<div class="storeengine-review-form-media" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-max="<?php echo esc_attr( $media_max ); ?>" data-max-size="<?php echo esc_attr( $media_max_size ); ?>">
										<label class="storeengine-review-form-media__label">
											<span class="storeengine-review-form-media__icon" aria-hidden="true"></span>
											<?php esc_html_e( 'Add photos or videos', 'storeengine' ); ?>
											<input type="file" class="storeengine-review-form-media__input" accept="image/*,video/*" multiple />
										</label>
										<div class="storeengine-review-form-media__previews" aria-live="polite">
											<?php foreach ( $media as $m ) : ?>
												<div class="storeengine-review-form-media__preview" data-id="<?php echo esc_attr( $m['id'] ); ?>">
													<?php if ( 'video' === $m['type'] ) : ?>
														<video src="<?php echo esc_url( $m['url'] ); ?>" muted playsinline></video>
													<?php else : ?>
														<img src="<?php echo esc_url( $m['thumb'] ); ?>" alt="" />
													<?php endif; ?>
													<button type="button" class="storeengine-review-form-media__remove" aria-label="<?php esc_attr_e( 'Remove', 'storeengine' ); ?>">&times;</button>
												</div>
											<?php endforeach; ?>
										</div>
										<input type="hidden" class="storeengine-review-form-media__ids" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $media, 'id' ) ) ); ?>" />
									</div>

									<div class="storeengine-dashboard-review-edit__actions">
										<button type="button" class="storeengine-btn storeengine-btn--sm storeengine-btn--bg-blue storeengine-dashboard-review-edit__save"><?php echo $storeengine_is_edit ? esc_html__( 'Save changes', 'storeengine' ) : esc_html__( 'Submit review', 'storeengine' ); ?></button>
										<button type="button" class="storeengine-btn storeengine-btn--sm storeengine-btn--outline storeengine-dashboard-review-edit__cancel"><?php esc_html_e( 'Cancel', 'storeengine' ); ?></button>
									</div>
								</div>
							</td>
						</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
