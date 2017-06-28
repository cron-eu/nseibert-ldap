<?php
namespace NormanSeibert\Ldap\ViewHelpers;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Felix Kopp <felix-source@phorax.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Imaging\IconFactory;

/**
 * Displays sprite icon identified by iconName key
 *
 * @author Felix Kopp <felix-source@phorax.com>
 */
class SpriteManagerIconViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper {

    protected $escapeOutput = false;
	/**
	 * Prints sprite icon html for $iconName key
	 *
	 * @param string $iconName
	 * @param array $options
	 * @return string
	 */
	public function render($iconName, $options = array()) {
        $iconFactory = $this->objectManager->get(IconFactory::class);
        return $iconFactory->getIcon($iconName, $options);
	}

}

?>
