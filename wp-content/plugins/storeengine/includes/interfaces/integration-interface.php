<?php
namespace StoreEngine\Interfaces;

interface IntegrationInterface {

	public function setup(): void;

	public function dispatch_hooks(): void;

	public function get_id(): string;

	public function get_label(): string;

	public function get_logo(): string;

	public function enabled(): bool;
}

// End of file integration-interface.php
