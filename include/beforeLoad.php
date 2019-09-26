<?php

if ( ! getenv( 'CI' ) ) {
	$dotenv = new Dotenv\Dotenv( __DIR__ . '/../' );
	$dotenv->load();
}
