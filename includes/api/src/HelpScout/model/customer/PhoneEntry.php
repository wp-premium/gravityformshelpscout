<?php
namespace HelpScout\model\customer;

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class PhoneEntry extends CustomerEntry {
	
	/**
	 * @return string
	 */
	public function getType() {
		return parent::getLocation();
	}
}