<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\Tag;

abstract class SubstituteTag extends Tag
{

	public function close(&$ahtml, &$ihtml)
	{
		$tag = $this->getTagName();
		if ($this->mpdf->InlineProperties[$tag]) {
			$this->mpdf->restoreInlineProperties($this->mpdf->InlineProperties[$tag]);
		}
		unset($this->mpdf->InlineProperties[$tag]);
		$ltag = strtolower($tag);
		$this->mpdf->$ltag = false;
	}
}
