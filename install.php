<?php
/**
 * Elgg install script
 *
 * @package Elgg
 * @subpackage Core
 * @link http://elgg.org/
 */

// check for PHP 4 before we do anything else
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
    echo "Your server's version of PHP (" . PHP_VERSION . ") is too old to run Elgg.\n";
	exit;
}

// If we're already installed, go back to the homepage
// @todo

require_once(dirname(__FILE__) . "/install/ElggInstaller.php");

$installer = new ElggInstaller();

$step = get_input('step', 'welcome');
$installer->run($step);
