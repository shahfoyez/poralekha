<?php
/**
 * @license MIT
 *
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\PsrLogAwareTrait;

use StoreEngine\Psr\Log\LoggerInterface;

trait PsrLogAwareTrait 
{

	/**
	 * @var \StoreEngine\Psr\Log\LoggerInterface
	 */
	protected $logger;

	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}
	
}
