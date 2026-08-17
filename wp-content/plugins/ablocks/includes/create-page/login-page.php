<?php
namespace ABlocks\CreatePage;

use ABlocks\CreatePage\Page\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class LoginPage extends Common {


	protected string $page_type = 'login';
	protected ?string $settings_field = 'login_page';

	protected string $slug = 'login';
	protected string $title = 'Login';
	protected string $content;
	public function __construct() {
		$this->content = $this->build_content();
	}
	private function build_content(): string {
		ob_start();
		?>
		<!-- wp:ablocks/form-builder {"block_id":"202fffda-0ea6-4a07-8c7a-ce738dbc8488","variationSelected":false,"postId":787,"formType":"login","formActions":"","childDetails":[{"name":"username","inputType":"Username"},{"name":"password","inputType":"Password"},{"name":"rememberme","inputType":""}],"buttonText":"Log In","mapOtherOptions":[{"label":"USERNAME","value":"username"},{"label":"PASSWORD","value":"password"},{"label":"REMEMBERME","value":"rememberme"},{"label":"None","value":"default"}]} -->
		<div class="ablocks-block ablocks-block-202fffda-0ea6-4a07-8c7a-ce738dbc8488 ablocks-block--form-builder"><div class="ablocks-block-container"><form id="ablocks-form-builder-202fffda-0ea6-4a07-8c7a-ce738dbc8488" class="ablocks-form-builder" method="post"><input type="hidden" name="security" value=""/><input type="hidden" name="action" value="ablocks/form_builder_login_handler"/><input type="hidden" name="current_post_id" value="787"/><input type="hidden" name="block_id" value="202fffda-0ea6-4a07-8c7a-ce738dbc8488"/><!-- wp:ablocks/form-input {"block_id":"7dae34b2-4413-4f23-9078-afab6ac3b915","label":"Username or Email","formType":"login","name":"username","placeholder":"Username or Email","inputType":"Username","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-7dae34b2-4413-4f23-9078-afab6ac3b915 ablocks-block--form-input ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="7dae34b2-4413-4f23-9078-afab6ac3b915">Username or Email</label><input class="ablocks-form-builder__input false" placeholder="Username or Email" name="username" id="7dae34b2-4413-4f23-9078-afab6ac3b915" type="text"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-input -->

		<!-- wp:ablocks/form-password {"block_id":"a66d09a9-65f8-47a5-9d1a-81827e449fcc","label":"Password","name":"password","placeholder":"Password","formType":"login","inputType":"Password","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-a66d09a9-65f8-47a5-9d1a-81827e449fcc ablocks-block--form-password ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="a66d09a9-65f8-47a5-9d1a-81827e449fcc">Password</label><input class="ablocks-form-builder__input false" placeholder="Password" name="password" id="a66d09a9-65f8-47a5-9d1a-81827e449fcc" type="password"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-password -->

		<!-- wp:ablocks/form-checkbox {"block_id":"1e73227a-b5b0-416d-b1e5-56c7d14ba8ff","label":"Remember Me","name":"rememberme"} -->
		<div data-required="false" class="ablocks-block ablocks-block-1e73227a-b5b0-416d-b1e5-56c7d14ba8ff ablocks-block--form-checkbox ablocks-form-builder__field ablocks-form-builder__checkbox"><div class="ablocks-form-builder__field__content"><input type="checkbox" name="rememberme" id="remember_me_1e73227a-b5b0-416d-b1e5-56c7d14ba8ff"/><label class="ablocks-form-builder__label  " for="remember_me_1e73227a-b5b0-416d-b1e5-56c7d14ba8ff">Remember Me</label></div><div class="ablocks-block-error-wrap"></div></div>
		<!-- /wp:ablocks/form-checkbox --><button class="ablocks-form-builder__submit-button ablocks-form-builder__submit-button--full-width" type="submit">Log In</button></form><div class="ablocks-block--form-builder__feedback-message"></div></div></div>
		<!-- /wp:ablocks/form-builder -->
		<?php
		return ob_get_clean();
	}
}
