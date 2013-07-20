<?php
/**
 * Plugin Configuration File
 *
 * In order to make the plugin work you must include this file
 * within either your apps 'core.php' or 'bootstrap.php'.
 *
 * Please override defaults
 *
 * Copyright (c) 2010 Anthony Putignano
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP version 5.2
 * CakePHP version 1.3
 *
 * @package    braintree
 * @subpackage braintree.config
 * @copyright  2010 Anthony Putignano <anthonyp@xonatek.com>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/anthonyp/braintree
 */

Braintree_Configuration::environment(Configure::read('Braintree.environment'));
Braintree_Configuration::merchantId(Configure::read('Braintree.merchantId'));
Braintree_Configuration::publicKey(Configure::read('Braintree.publicKey'));
Braintree_Configuration::privateKey(Configure::read('Braintree.privateKey'));

?>
