<?php
/* Initialize Joomla framework */
define('_JEXEC', 1);
define('JPATH_BASE', dirname('../../../..') );
define( 'DS', DIRECTORY_SEPARATOR );
/* Required Files */
require_once( JPATH_BASE .DS. 'includes' .DS. 'defines.php' );
require_once( JPATH_BASE .DS. 'includes' .DS. 'framework.php' );
/* To use Joomla's Database Class */
require_once(JPATH_BASE .DS.'libraries'.DS.'joomla'.DS.'factory.php' );

/* Insert Nochex Payment Plugin Into Extensions Table */

	$db = JFactory::getDBO();

	$query = "INSERT INTO #__extensions (extension_id, name, type, element, folder, client_id, enabled, access, protected) VALUES (11117, 'VM - Payment, Nochex', 'plugin', 'nochex', 'vmpayment', 0, 1, 1, 0)";

	$db->setQuery($query);
	$db->query();

?>