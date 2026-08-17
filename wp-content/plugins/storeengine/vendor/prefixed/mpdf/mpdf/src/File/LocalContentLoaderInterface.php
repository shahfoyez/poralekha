<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\File;

interface LocalContentLoaderInterface
{

	/**
	 * @return string|null
	 */
	public function load($path);

}
