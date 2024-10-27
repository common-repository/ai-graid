<?php

namespace AIGrAid\Plugin;

class Boot {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_carbon_fields();
	}

	/**
	 * Initialize the carbon fields
	 * @return void
	 */
	private function setup_carbon_fields() {
		add_action( 'after_setup_theme', function () {
			\Carbon_Fields\Carbon_Fields::boot();
		} );

		new Metadata();
		new Options();
		new Hooks();
	}
}