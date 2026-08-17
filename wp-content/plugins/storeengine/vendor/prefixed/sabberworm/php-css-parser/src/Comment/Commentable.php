<?php
/**
 * @license MIT
 *
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Sabberworm\CSS\Comment;

interface Commentable
{
    /**
     * @param array<array-key, Comment> $aComments
     *
     * @return void
     */
    public function addComments(array $aComments);

    /**
     * @return array<array-key, Comment>
     */
    public function getComments();

    /**
     * @param array<array-key, Comment> $aComments
     *
     * @return void
     */
    public function setComments(array $aComments);
}
