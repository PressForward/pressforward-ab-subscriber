<?php

/*
Plugin Name: PressForward Academic Blogs Subscriber
Plugin URI: http://pressforward.org/
Description: This plugin is an adds subscriptions from the Academic Blogs directory to PressForward.
Version: 1.0.1
Author: Aram Zucker-Scharff, Boone B Gorges
Author URI: http://aramzs.me, http://boone.gorg.es/
License: GPL2
*/

/*  Developed for the Center for History and New Media

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PF_AB_ROOT', dirname(__FILE__) );
define( 'PF_AB_FILE_PATH', PF_AB_ROOT . '/' . basename(__FILE__) );
define( 'PF_AB_URL', plugins_url('/', __FILE__) );

/**
 * Bootstrap
 *
 * You can also use this to get a value out of the global, eg
 *
 *    $foo = pressforward_ab_subscriber()->bar;
 *
 * @since 1.0
 */
function pressforward_ab_subscriber() {
	require( dirname( __FILE__ ) . '/includes/pressforward-ab-subscribe.php' );
	return PF_AB_Subscriber::init();
}
add_action( 'pressforward_init', 'pressforward_ab_subscriber' );
