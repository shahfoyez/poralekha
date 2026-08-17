<?php

namespace StoreEngine\Addons\Email\order;

class AbandonedCartFirst extends AbstractAbandonedCartMail {

	public function __construct() {
		parent::__construct('abandoned_cart_first');
	}
}
