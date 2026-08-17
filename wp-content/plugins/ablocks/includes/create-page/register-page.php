<?php
namespace ABlocks\CreatePage;

use ABlocks\CreatePage\Page\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class RegisterPage extends Common {


	protected string $page_type = 'register';
	protected ?string $settings_field = 'registration_page';

	protected string $slug = 'register ';
	protected string $title = 'Register';
	protected string $content;
	public function __construct() {
		$this->content = $this->build_content();
	}
	private function build_content(): string {
		ob_start();
		?>
		<!-- wp:ablocks/form-builder {"block_id":"5bad4545-6377-4604-b484-7d26dc04c45f","variationSelected":false,"postId":787,"formType":"registration","childDetails":[{"name":"username","inputType":"Username"},{"name":"email","inputType":"Email"},{"name":"password","inputType":"Password"},{"name":"confirm_password","inputType":"Password"},{"name":"accept","inputType":"checkbox"}],"buttonText":"Register","mapEmailOptions":[{"label":"None","value":"default"},{"label":"Email","value":"email"}],"mapOtherOptions":[{"label":"USERNAME","value":"username"},{"label":"EMAIL","value":"email"},{"label":"PASSWORD","value":"password"},{"label":"CONFIRM_PASSWORD","value":"confirm_password"},{"label":"ACCEPT","value":"accept"},{"label":"None","value":"default"}],"userRoles":[{"label":"Default","value":"default"},{"label":"Administrator","value":"administrator"},{"label":"Editor","value":"editor"},{"label":"Author","value":"author"},{"label":"Contributor","value":"contributor"},{"label":"Subscriber","value":"subscriber"},{"label":"Customer","value":"customer"},{"label":"Shop manager","value":"shop_manager"},{"label":"Tutor Instructor","value":"tutor_instructor"},{"label":"StoreEngine Customer","value":"storeengine_customer"},{"label":"StoreEngine Shop manager","value":"storeengine_shop_manager"},{"label":"Academy Student","value":"academy_student"},{"label":"Academy Instructor","value":"academy_instructor"},{"label":"StoreEngine Affiliate","value":"storeengine_affiliate"}],"roleSlug":"default"} -->
		<div class="ablocks-block ablocks-block-5bad4545-6377-4604-b484-7d26dc04c45f ablocks-block--form-builder"><div class="ablocks-block-container"><form id="ablocks-form-builder-5bad4545-6377-4604-b484-7d26dc04c45f" class="ablocks-form-builder" method="post"><input type="hidden" name="security" value=""/><input type="hidden" name="action" value="ablocks/form_builder_registration_handler"/><input type="hidden" name="current_post_id" value="787"/><input type="hidden" name="block_id" value="5bad4545-6377-4604-b484-7d26dc04c45f"/><!-- wp:ablocks/form-input {"block_id":"640a2ecd-11b9-46e6-a360-624cf2125d56","label":"Username","formType":"registration","name":"username","placeholder":"Username","inputType":"Username","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-640a2ecd-11b9-46e6-a360-624cf2125d56 ablocks-block--form-input ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="640a2ecd-11b9-46e6-a360-624cf2125d56">Username</label><input class="ablocks-form-builder__input false" placeholder="Username" name="username" id="640a2ecd-11b9-46e6-a360-624cf2125d56" type="text"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-input -->

		<!-- wp:ablocks/form-input {"block_id":"1b16e249-9fa4-4411-aabc-fd5500c2bdab","label":"Email","formType":"registration","name":"email","placeholder":"Email","inputType":"Email","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-1b16e249-9fa4-4411-aabc-fd5500c2bdab ablocks-block--form-input ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="1b16e249-9fa4-4411-aabc-fd5500c2bdab">Email</label><input class="ablocks-form-builder__input false" placeholder="Email" name="email" id="1b16e249-9fa4-4411-aabc-fd5500c2bdab" type="email"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-input -->

		<!-- wp:ablocks/form-password {"block_id":"174b2064-b2ae-4720-8df2-c00611ec2bea","label":"Password","name":"password","placeholder":"Password","formType":"registration","inputType":"Password","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-174b2064-b2ae-4720-8df2-c00611ec2bea ablocks-block--form-password ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="174b2064-b2ae-4720-8df2-c00611ec2bea">Password</label><input class="ablocks-form-builder__input false" placeholder="Password" name="password" id="174b2064-b2ae-4720-8df2-c00611ec2bea" type="password"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-password -->

		<!-- wp:ablocks/form-password {"block_id":"345c98b6-f0d7-4576-a5d3-45df514f6e64","label":"Confirm Password","name":"confirm_password","placeholder":"Confirm password","formType":"registration","inputType":"Password","nameChangeable":false} -->
		<div data-required="true" class="ablocks-block ablocks-block-345c98b6-f0d7-4576-a5d3-45df514f6e64 ablocks-block--form-password ablocks-form-builder__field"><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="345c98b6-f0d7-4576-a5d3-45df514f6e64">Confirm Password</label><input class="ablocks-form-builder__input false" placeholder="Confirm password" name="confirm_password" id="345c98b6-f0d7-4576-a5d3-45df514f6e64" type="password"/><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-password -->

		<!-- wp:ablocks/form-checkbox {"block_id":"0a3eaa2d-7efa-4b74-ae02-d631a72e986c","label":"Accept our terms and condition","inputType":"checkbox","name":"accept","isRequired":true} -->
		<div data-required="true" class="ablocks-block ablocks-block-0a3eaa2d-7efa-4b74-ae02-d631a72e986c ablocks-block--form-checkbox ablocks-form-builder__field ablocks-form-builder__checkbox"><div class="ablocks-form-builder__field__content"><input type="checkbox" name="accept" id="accept_our_terms_and_condition_0a3eaa2d-7efa-4b74-ae02-d631a72e986c"/><label class="ablocks-form-builder__label ablocks-form-builder__label--required " for="accept_our_terms_and_condition_0a3eaa2d-7efa-4b74-ae02-d631a72e986c">Accept our terms and condition</label></div><div class="ablocks-block-error-wrap"><div class="ablocks-block-error-msg">This field is required</div></div></div>
		<!-- /wp:ablocks/form-checkbox --><button class="ablocks-form-builder__submit-button ablocks-form-builder__submit-button--full-width" type="submit">Register</button></form><div class="ablocks-block--form-builder__feedback-message"></div></div></div>
		<!-- /wp:ablocks/form-builder -->
		<?php
		return ob_get_clean();
	}
}
