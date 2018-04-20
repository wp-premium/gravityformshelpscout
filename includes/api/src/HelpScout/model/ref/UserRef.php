<?php
namespace HelpScout\model\ref;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class UserRef extends PersonRef {
	public function __construct($data=null) {
		parent::__construct($data);
		$this->setType('user');
	}
}
