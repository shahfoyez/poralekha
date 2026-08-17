<?php
namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Post\Dashboard;
use StoreEngine\Post\ForgotPassword;
use StoreEngine\Post\Register;

class Post {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}
	public function dispatch_hooks() {
		( new Dashboard() )->dispatch_actions();
		( new ForgotPassword() )->dispatch_actions();
		( new Register() )->dispatch_actions();
	}
}
