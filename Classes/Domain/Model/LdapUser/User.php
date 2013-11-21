<?php
namespace NormanSeibert\Ldap\Domain\Model\LdapUser;
/**
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 * 
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * This copyright notice MUST APPEAR in all copies of the script!
 * 
 * @package   ldap
 * @author	  Norman Seibert <seibert@entios.de>
 * @copyright 2013 Norman Seibert
 */

/**
 * Model for users read from LDAP server
 */
class User extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {
	
	/**
	 *
	 * @var string 
	 */
	protected $dn;
	
	/**
	 *
	 * @var array 
	 */
	protected $attributes;
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\LdapServer\Server 
	 */
	protected $ldapServer;
	
	/**
	 * @var \NormanSeibert\Ldap\Domain\Model\Configuration\Configuration
	 * @inject
	 */
	protected $ldapConfig;
	
	/**
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 */
	protected $objectManager;
	
	/**
	 *
	 * @var \NormanSeibert\Ldap\Domain\Model\UserInterface
	 */
	protected $user;
	
	/**
	 * 
	 */
	public function __construct() {
		$this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->cObj = $this->objectManager->create('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$this->importGroups = 1;
	}
	
	/**
	 * 
	 * @param string $dn
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function setDN($dn) {
		$this->dn = $dn;
		return $this;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getDN() {
		return $this->dn;
	}
	
	/**
	 * 
	 * @param array $attrs
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function setAttributes($attrs) {
		$this->attributes = $attrs;
		return $this;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}
	
	/**
	 * 
	 * @param string $attr
	 * @param string $value
	 * @return \NormanSeibert\Ldap\Domain\Model\LdapUser\User
	 */
	public function setAttribute($attr, $value) {
		$this->attributes[$attr] = $value;
		return $this;
	}
	
	/**
	 * 
	 * @param string $attr
	 * @return array
	 */
	public function getAttribute($attr) {
		return $this->attributes[$attr];
	}
	
	/** 
	 * Tries to load the TYPO3 user based on DN or username
	 * 
	 */
	public function loadUser() {
		$pid = $this->userRules->getPid();
		$msg = 'Search for user record with DN = ' . $this->dn . ' in page ' . $pid;
		if ($this->ldapConfig->logLevel == 2) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
		}
		// search for DN
		$user = $this->userRepository->findByDn($this->dn, $pid);
		// search for Username if no record with DN found
		if (is_object($user)) {
			$msg = 'User record already existing: ' . $user->getUid();
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
			}
		} else {
			$mapping = $this->userRules->getMapping();
			
			$rawLdapUsername = $mapping['username.']['data'];
			$ldapUsername = str_replace('field:', '', $rawLdapUsername);
			$username = $this->getAttribute($ldapUsername);
			$user = $this->userRepository->findByUsername($username, $pid);
			if (is_object($user)) {
				$msg = 'User record already existing: ' . $user->getUid();
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 0);
				}
			}
		}
		$this->user = $user;
	}
	
	/** Maps attributes from LDAP record to TYPO3 DB fields
	 * 
	 * @param string $mappingType
	 * @param array $useAttributes
	 * @return array
	 */
	protected function mapAttributes($mappingType = 'user', $useAttributes = array()) {
		$insertArray = array();
		if ($mappingType == 'group') {
			$mapping = $this->userRules->getGroupRules()->getMapping();
			$attributes = $useAttributes;
		} else {
			$mapping = $this->userRules->getMapping();
			$attributes = $this->attributes;
		}

		if (is_array($mapping)) {
			foreach ($mapping as $key => $value) {
				$stdWrap = $value['stdWrap.'];
				if (is_array($value['stdWrap.'])) {
					unset($value['stdWrap.']);
				}
				$this->cObj->alternativeData = $attributes;
				$result = $this->cObj->stdWrap($value['value'], $value);
				if (substr($key, strlen($key) - 1, 1) == '.') {
					$key = substr($key, 0, strlen($key) - 1);
				}
				if (is_array($result)) {
					unset($result['count']);
					$attr = array();
					foreach ($result as $v) {
						$attr[] = $this->cObj->stdWrap($v, $stdWrap);
					}
					$result = implode(', ', $attr);
				} else {
					$result = $this->cObj->stdWrap($result, $stdWrap);
				}
				$insertArray[$key] = $result;
			}
		}
		
		return $insertArray;
	}
	
	/** Assigns TYPO3 usergroups to the current TYPO3 user
	 * 
	 * @param string $lastRun
	 * @return array
	 */
	protected function assignGroups($lastRun = NULL) {
		$mapping = $this->userRules->getGroupRules()->getMapping();
		if (is_array($mapping)) {
			if ($this->userRules->getGroupRules()->getReverseMapping()) {
				$ret = $this->reverseAssignGroups($lastRun);
			} else {
				switch (strtolower($mapping['field'])) {
					case 'text':
						$ret = $this->assignGroupsText();
						break;
					case 'parent':
						$ret = $this->assignGroupsParent();
						break;
					case 'dn':
						$ret = $this->assignGroupsDN();					
						break;
					default:
				}
			}
		} else {
			$ret = array(
				'newGroups' => array(),
				'existingGroups' => array()
			);
		}
		
		$assignedGroups = $this->addNewGroups($ret['newGroups'], $ret['existingGroups'], $lastRun);

		return $assignedGroups;
	}
	
	/** Assigns TYPO3 usergroups to the current TYPO3 user by additionally querying the LDAP server for groups
	 * 
	 * @param string $lastRun
	 * @return array
	 */
	private function reverseAssignGroups($lastRun = NULL) {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$groupname = mb_strtolower($this->getAttribute('dn'));
		
		/*
		$this->userRules->setBaseDN($this->userRules->getGroupRules()->getBaseDN());
		$this->userRules->setFilter($this->userRules->getGroupRules()->getFilter());
		$this->userRules->setMapping($this->userRules->getGroupRules()->getMapping());
		$this->userRules->setGroupRules($this->objectManager->create('NormanSeibert\\Ldap\\Domain\\Model\\LdapServer\\ServerConfigurationGroups'));
		*/
		
		$ldapGroups = $this->ldapServer->getGroups($groupname);
		
		foreach ($ldapGroups as $group) {
			$this->cObj->alternativeData = $group;
			$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
			$tmp = $this->resolveGroup('Title', $usergroup, $usergroup, $group['dn']);
			if ($tmp['newGroup']) {
				$ret['newGroups'][] = $tmp['newGroup'];
			}
			if ($tmp['existingGroup']) {
				$ret['existingGroups'][] = $tmp['existingGroup'];
			}
		}
		
		// $assignedGroups = $this->addNewGroups($ret['newGroups'], $ret['existingGroups'], $lastRun);

		return $ret;
	}
	
	/** Determines usergroups based on a text attribute
	 * 
	 * @param array $mapping
	 * @return array
	 */
	private function assignGroupsText($mapping) {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$this->cObj->alternativeData = $this->attributes;
		$usergroups = $this->cObj->stdWrap('', $mapping['title.']);
		
		if (is_array($usergroups)) {
			foreach ($usergroups as $group) {
				$tmp = $this->resolveGroup('Title', $group, $group);
				if ($tmp['newGroup']) {
					$ret['newGroups'][] = $tmp['newGroup'];
				}
				if ($tmp['existingGroup']) {
					$ret['existingGroups'][] = $tmp['existingGroup'];
				}
			}
		} elseif ($usergroups) {
			$tmp = $this->resolveGroup('Title', $usergroups, $usergroups);
			if ($tmp['newGroup']) {
				$ret['newGroups'][] = $tmp['newGroup'];
			}
			if ($tmp['existingGroup']) {
				$ret['existingGroups'][] = $tmp['existingGroup'];
			}
		}
		
		return $ret;
	}
	
	/** Determines usergroups based on the user records parent record
	 * 
	 * @param array $mapping
	 * @return array
	 */
	private function assignGroupsParent() {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$path = explode(',', $this->dn);
		unset($path[0]);
		$parentDN = implode(',', $path);
		$ldapGroup = $this->ldapServer->getGroup($parentDN);
		
		$this->cObj->alternativeData = $ldapGroup;
		$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
		
		if ($usergroup) {
			$tmp = $this->resolveGroup('Title', $usergroup, $usergroup, $ldapGroup['dn']);
			if ($tmp['newGroup']) {
				$ret['newGroups'][] = $tmp['newGroup'];
			}
			if ($tmp['existingGroup']) {
				$ret['existingGroups'][] = $tmp['existingGroup'];
			}
		}
		
		return $ret;
	}
	
	/** Determines usergroups based on the user record's DN
	 * 
	 * @return array
	 */
	private function assignGroupsDN() {
		$ret = array();
		$mapping = $this->userRules->getGroupRules()->getMapping();
		
		$this->cObj->alternativeData = $this->attributes;
		$groupDNs = $this->cObj->stdWrap('', $mapping['field.']);
		
		if (is_array($groupDNs)) {
			unset($groupDNs['count']);
			foreach ($groupDNs as $groupDN) {
				$ldapGroup = $this->ldapServer->getGroup($groupDN);
				if (is_array($ldapGroup)) {
					$this->cObj->alternativeData = $ldapGroup;
					$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
//					\TYPO3\CMS\Core\Utility\DebugUtility::debug($usergroup);
					$tmp = $this->resolveGroup('dn', $groupDN, $usergroup, $groupDN);
					if ($tmp['newGroup']) {
						$ret['newGroups'][] = $tmp['newGroup'];
					}
					if ($tmp['existingGroup']) {
						$ret['existingGroups'][] = $tmp['existingGroup'];
					}
				}
			}
		} elseif ($groupDNs) {
			$ldapGroup = $this->ldapServer->getGroup($groupDNs);
			$this->cObj->alternativeData = $ldapGroup;
			$usergroup = $this->cObj->stdWrap('', $mapping['title.']);
			$tmp = $this->resolveGroup('dn', $groupDNs, $usergroup, $groupDNs);
			if ($tmp['newGroup']) {
				$ret['newGroups'][] = $tmp['newGroup'];
			}
			if ($tmp['existingGroup']) {
				$ret['existingGroups'][] = $tmp['existingGroup'];
			}
		}
		
//		\TYPO3\CMS\Core\Utility\DebugUtility::debug($ret['newGroups']);
//		\TYPO3\CMS\Core\Utility\DebugUtility::debug(count($ret['existingGroups']));
		
//		exit();
		
		return $ret;
	}
	
	/**
	 * 
	 * @param string $attribute
	 * @param string $selector
	 * @param array $usergroup
	 * @param string $dn
	 * @param object $obj
	 * @return array
	 */
	private function resolveGroup($attribute, $selector, $usergroup, $dn = NULL, $obj = NULL) {
		$groupFound = FALSE;
		$resolvedGroup = FALSE;
		$newGroup = FALSE;

		$allGroups = $this->ldapServer->getAllGroups();
		foreach ($allGroups as $group) {
			$attrValue = $group->__get($attribute);
			if ($selector == $attrValue) {
				$groupFound = $group;
			}
		}
		if (is_object($groupFound)) {
			if ($this->checkGroupMembership($groupFound->getTitle())) {
				$resolvedGroup = $groupFound;
			}
		} elseif ($usergroup) {
			if ($this->checkGroupMembership($usergroup)) {
				$newGroup = array(
					'title' => $usergroup,
					'dn' => $dn,
					'groupObject' => $obj
				);
			}
		}		
		$ret = array(
			'newGroup' => $newGroup,
			'existingGroup' => $resolvedGroup
		);
		
		return $ret;
	}
	
	/** Checks whether a usergroup is in the list of allowed groups
	 * 
	 * @param string $groupname
	 * @return boolean
	 */
	protected function checkGroupMembership($groupname) {
		$ret = FALSE;
		$onlygroup = $this->userRules->getGroupRules()->getRestrictToGroups();
		if (empty($onlygroup)) {
			$ret = TRUE;
		} else {
			$onlygrouparray = explode(",", $onlygroup);
			if (is_array($onlygrouparray)) {
				$ret = \TYPO3\CMS\Core\Utility\GeneralUtility::inArray($onlygrouparray, $groupname);
			}
			if (($ret == FALSE) && ($this->ldapConfig->logLevel == 2)) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog('Filtered out: ' . $groupname, 'ldap', 0);
			}
		}

		return $ret;
	}
	
	/**
	 * adds TYPO3 usergroups to the user record
	 * 
	 * @param string $lastRun
	 */
	protected function addUsergroupsToUserRecord($lastRun = NULL) {
		if (is_object($this->userRules->getGroupRules())) {
			$assignedGroups = $this->assignGroups($lastRun);
			if ($this->userRules->getGroupRules()->getAddToGroups()) {
				$addToGroups = $this->userRules->getGroupRules()->getAddToGroups();
				$groupsToAdd = $this->usergroupRepository->findByUids(explode(',', $addToGroups));
				$usergroups = array_merge($assignedGroups, $groupsToAdd);
			} else {
				$usergroups = $assignedGroups;
			}
			if (count($usergroups) == 0) {
				$msg = 'User has no usergroup';
				if ($this->ldapConfig->logLevel == 2) {
					\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
				}
				\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
			}
			foreach ($usergroups as $group) {
				$this->user->addUsergroup($group);
			}
		} else {
			$msg = 'User has no usergroup';
			if ($this->ldapConfig->logLevel == 2) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::devLog($msg, 'ldap', 2);
			}
			\NormanSeibert\Ldap\Utility\Helpers::addError(\TYPO3\CMS\Core\Messaging\FlashMessage::WARNING, $msg, $this->ldapServer->getConfiguration()->getUid());
		}
	}
	
	/**
	 * removes usergroups from the user record
	 */
	protected function removeUsergroupsFromUserRecord() {
		$preserveNonLdapGroups = $this->userRules->getGroupRules()->getPreserveNonLdapGroups();
		if ($preserveNonLdapGroups) {
			$usergroups = $this->user->getUsergroup();
			$removeGroups = array();
			// iterate two times because "remove" shortens the iterator otherwise
			foreach ($usergroups as $group) {
				if ($group->getServerUid()) {
					$removeGroups[] = $group;
				}
			}
			foreach ($removeGroups as $group) {
				$this->user->removeUsergroup($group);
			}
		} else {
			$usergroup = $this->objectManager->create('TYPO3\CMS\Extbase\Persistence\ObjectStorage');
			$this->user->setUsergroup($usergroup);
		}
	}
}
?>