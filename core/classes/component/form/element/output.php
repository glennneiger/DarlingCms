<?php
/**
 * Created by PhpStorm.
 * User: sevidmusic
 * Date: 5/21/17
 * Time: 1:17 AM
 */

namespace DarlingCms\classes\component\form\element;


class output extends formElement
{
    public function __construct(array $elementAttributes = array(), $elementContent = '')
    {
        parent::__construct('output', $elementAttributes, $elementContent);
    }

}