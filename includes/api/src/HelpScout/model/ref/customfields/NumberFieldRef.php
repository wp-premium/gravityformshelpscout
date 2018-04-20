<?php
namespace HelpScout\model\ref\customfields;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
    die();
}

use HelpScout\ValidationException;

class NumberFieldRef extends AbstractCustomFieldRef
{

    public function validate($value)
    {
        if (!is_numeric($value)) {
            throw new ValidationException('The value must be numeric');
        }
    }
}
