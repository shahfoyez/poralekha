<?php

namespace StoreEngine\Addons\Email\order;

class AbandonedCartSecond extends AbstractAbandonedCartMail {

	public function __construct() {
		parent::__construct('abandoned_cart_second');
	}

}
