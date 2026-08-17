<?php

namespace StoreEngine\Addons\Email\order;

class AbandonedCartThird extends AbstractAbandonedCartMail {

	public function __construct() {
		parent::__construct('abandoned_cart_third');
	}

}
