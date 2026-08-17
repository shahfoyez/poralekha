<?php
namespace ABlocks\CreatePage;

use ABlocks\CreatePage\Page\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ForgetPasswordPage extends Common {


	protected string $page_type = 'forget_password';
	protected ?string $settings_field = 'forget_password_page';

	protected string $slug = 'forget_password';
	protected string $title = 'Forget password';
	protected string $content;
	public function __construct() {
		$this->content = $this->build_content();
	}
	private function build_content(): string {
		ob_start();
		?>
		<!-- wp:ablocks/form-builder {"block_id":"84896dd6-c2a9-478c-a11e-cfd5e2c3ba0d","variationSelected":false,"postId":787,"formType":"forget_password","formActions":"","childDetails":[{"name":"email","inputType":"Email"}],"buttonText":"Forget Password","mapEmailOptions":[{"label":"None","value":"default"},{"label":"Email","value":"email"}],"mapOtherOptions":[{"label":"EMAIL","value":"email"},{"label":"None","value":"default"}]} -->
		<div class="ablocks-block ablocks-block-84896dd6-c2a9-478c-a11e-cfd5e2c3ba0d ablocks-block--form-builder"><div class="ablocks-block-container"><form id="ablocks-form-builder-84896dd6-c2a9-478c-a11e-cfd5e2c3ba0d" class="ablocks-form-builder" method="post"><input type="hidden" name="security" value=""/><input type="hidden" name="action" value="ablocks/form_builder_forget_password_handler"/><input type="hidden" name="current_post_id" value="787"/><input type="hidden" name="block_id" value="84896dd6-c2a9-478c-a11e-cfd5e2c3ba0d"/><!-- wp:ablocks/form-input {"block_id":"7afd891a-b136-463e-82d1-c0741dda7e40","label":"Email","formType":"forget_password","name":"email","placeholder":"Email","inputType":"Email","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-7afd891a-b136-463e-82d1-c0741dda7e40 ablocks-block--form-input ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="7afd891a-b136-463e-82d1-c0741dda7e40">Email</label><input class="ablocks-form-builder__input false" placeholder="Email" name="email" id="7afd891a-b136-463e-82d1-c0741dda7e40" type="email"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-input --><button class="ablocks-form-builder__submit-button ablocks-form-builder__submit-button--full-width" type="submit">Forget Password</button></form><div class="ablocks-block--form-builder__feedback-message"></div></div></div>
		<!-- /wp:ablocks/form-builder -->
		<?php
		return ob_get_clean();
	}
}
