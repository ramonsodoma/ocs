<?php

/**
 * @file Help.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Help
 * @ingroup help
 * 
 * @brief Provides methods for translating help topic keys to their respected topic
 * help ids.
 */

// $Id$


import('lib.pkp.classes.help.PKPHelp');

class Help extends PKPHelp {
	/**
	 * Constructor.
	 */
	function Help() {
		parent::PKPHelp();
		import('classes.help.OCSHelpMappingFile');
		$mainMappingFile = new OCSHelpMappingFile();
		$this->addMappingFile($mainMappingFile);
	}
}

?>
