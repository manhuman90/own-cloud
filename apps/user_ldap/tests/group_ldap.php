<?php
/**
* ownCloud
*
* @author Arthur Schiwon
* @copyright 2014 Arthur Schiwon <blizzz@owncloud.com>
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

namespace OCA\user_ldap\tests;

namespace OCA\user_ldap\tests;

use \OCA\user_ldap\GROUP_LDAP as GroupLDAP;
use \OCA\user_ldap\lib\Access;
use \OCA\user_ldap\lib\Connection;
use \OCA\user_ldap\lib\ILDAPWrapper;

class Test_Group_Ldap extends \PHPUnit_Framework_TestCase {
	private function getAccessMock() {
		static $conMethods;
		static $accMethods;

		if(is_null($conMethods) || is_null($accMethods)) {
			$conMethods = get_class_methods('\OCA\user_ldap\lib\Connection');
			$accMethods = get_class_methods('\OCA\user_ldap\lib\Access');
		}
		$lw  = $this->getMock('\OCA\user_ldap\lib\ILDAPWrapper');
		$connector = $this->getMock('\OCA\user_ldap\lib\Connection',
									$conMethods,
									array($lw, null, null));
		$um = new \OCA\user_ldap\lib\user\Manager(
				$this->getMock('\OCP\IConfig'),
				$this->getMock('\OCA\user_ldap\lib\FilesystemHelper'),
				$this->getMock('\OCA\user_ldap\lib\LogWrapper'),
				$this->getMock('\OCP\IAvatarManager'),
				$this->getMock('\OCP\Image')
			);
		$access = $this->getMock('\OCA\user_ldap\lib\Access',
								 $accMethods,
								 array($connector, $lw, $um));

		return $access;
	}

	private function enableGroups($access) {
		$access->connection->expects($this->any())
			   ->method('__get')
			   ->will($this->returnCallback(function() {
					return 1;
			   }));
	}

	public function testCountEmptySearchString() {
		$access = $this->getAccessMock();

		$this->enableGroups($access);

		$access->expects($this->any())
			->method('groupname2dn')
			->will($this->returnValue('cn=group,dc=foo,dc=bar'));

		$access->expects($this->any())
			->method('readAttribute')
			->will($this->returnValue(array('u11', 'u22', 'u33', 'u34')));

		// for primary groups
		$access->expects($this->once())
			->method('countUsers')
			->will($this->returnValue(2));

		$groupBackend = new GroupLDAP($access);
		$users = $groupBackend->countUsersInGroup('group');

		$this->assertSame(6, $users);
	}

	public function testCountWithSearchString() {
		$access = $this->getAccessMock();

		$this->enableGroups($access);

		$access->expects($this->any())
			->method('groupname2dn')
			->will($this->returnValue('cn=group,dc=foo,dc=bar'));

		$access->expects($this->any())
			->method('fetchListOfUsers')
			->will($this->returnValue(array()));

		$access->expects($this->any())
			->method('readAttribute')
			->will($this->returnCallback(function($name) {
				//the search operation will call readAttribute, thus we need
				//to anaylze the "dn". All other times we just need to return
				//something that is neither null or false, but once an array
				//with the users in the group ??? so we do so all other times for
				//simplicicity.
				if(strpos($name, 'u') === 0) {
					return strpos($name, '3');
				}
				return array('u11', 'u22', 'u33', 'u34');
			}));

		$access->expects($this->any())
			->method('dn2username')
			->will($this->returnCallback(function() {
				return 'foobar' . \OCP\Util::generateRandomBytes(7);
			}));

		$groupBackend = new GroupLDAP($access);
		$users = $groupBackend->countUsersInGroup('group', '3');

		$this->assertSame(2, $users);
	}

	public function testPrimaryGroupID2NameSuccess() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->will($this->returnValue('S-1-5-21-249921958-728525901-1594176202'));

		$access->expects($this->once())
			->method('searchGroups')
			->will($this->returnValue(array('cn=foo,dc=barfoo,dc=bar')));

		$access->expects($this->once())
			->method('dn2groupname')
			->with('cn=foo,dc=barfoo,dc=bar')
			->will($this->returnValue('MyGroup'));

		$groupBackend = new GroupLDAP($access);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame('MyGroup', $group);
	}

	public function testPrimaryGroupID2NameNoSID() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->will($this->returnValue(false));

		$access->expects($this->never())
			->method('searchGroups');

		$access->expects($this->never())
			->method('dn2groupname');

		$groupBackend = new GroupLDAP($access);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testPrimaryGroupID2NameNoGroup() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->will($this->returnValue('S-1-5-21-249921958-728525901-1594176202'));

		$access->expects($this->once())
			->method('searchGroups')
			->will($this->returnValue(array()));

		$access->expects($this->never())
			->method('dn2groupname');

		$groupBackend = new GroupLDAP($access);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testPrimaryGroupID2NameNoName() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$userDN = 'cn=alice,cn=foo,dc=barfoo,dc=bar';

		$access->expects($this->once())
			->method('getSID')
			->with($userDN)
			->will($this->returnValue('S-1-5-21-249921958-728525901-1594176202'));

		$access->expects($this->once())
			->method('searchGroups')
			->will($this->returnValue(array('cn=foo,dc=barfoo,dc=bar')));

		$access->expects($this->once())
			->method('dn2groupname')
			->will($this->returnValue(false));

		$groupBackend = new GroupLDAP($access);

		$group = $groupBackend->primaryGroupID2Name('3117', $userDN);

		$this->assertSame(false, $group);
	}

	public function testGetEntryGroupIDValue() {
		//tests getEntryGroupID via getGroupPrimaryGroupID
		//which is basically identical to getUserPrimaryGroupIDs
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$dn = 'cn=foobar,cn=foo,dc=barfoo,dc=bar';
		$attr = 'primaryGroupToken';

		$access->expects($this->once())
			->method('readAttribute')
			->with($dn, $attr)
			->will($this->returnValue(array('3117')));

		$groupBackend = new GroupLDAP($access);

		$gid = $groupBackend->getGroupPrimaryGroupID($dn);

		$this->assertSame('3117', $gid);
	}

	public function testGetEntryGroupIDNoValue() {
		//tests getEntryGroupID via getGroupPrimaryGroupID
		//which is basically identical to getUserPrimaryGroupIDs
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$dn = 'cn=foobar,cn=foo,dc=barfoo,dc=bar';
		$attr = 'primaryGroupToken';

		$access->expects($this->once())
			->method('readAttribute')
			->with($dn, $attr)
			->will($this->returnValue(false));

		$groupBackend = new GroupLDAP($access);

		$gid = $groupBackend->getGroupPrimaryGroupID($dn);

		$this->assertSame(false, $gid);
	}

	/**
	 * tests whether Group Backend behaves correctly when cache with uid and gid
	 * is hit
	 */
	public function testInGroupHitsUidGidCache() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$uid = 'someUser';
		$gid = 'someGroup';
		$cacheKey = 'inGroup'.$uid.':'.$gid;
		$access->connection->expects($this->once())
			->method('isCached')
			->with($cacheKey)
			->will($this->returnValue(true));

		$access->connection->expects($this->once())
			->method('getFromCache')
			->with($cacheKey)
			->will($this->returnValue(true));

		$access->expects($this->never())
			->method('username2dn');

		$groupBackend = new GroupLDAP($access);
		$groupBackend->inGroup($uid, $gid);
	}

	public function testGetGroupsWithOffset() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$access->expects($this->once())
			->method('ownCloudGroupNames')
			->will($this->returnValue(array('group1', 'group2')));

		$groupBackend = new GroupLDAP($access);
		$groups = $groupBackend->getGroups('', 2, 2);

		$this->assertSame(2, count($groups));
	}

	/**
	 * tests that a user listing is complete, if all it's members have the group
	 * as their primary.
	 */
	public function  testUsersInGroupPrimaryMembersOnly() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$access->connection->expects($this->any())
			->method('getFromCache')
			->will($this->returnValue(null));

		$access->expects($this->any())
			->method('readAttribute')
			->will($this->returnCallback(function($dn, $attr) {
				if($attr === 'primaryGroupToken') {
					return array(1337);
				}
				return array();
			}));

		$access->expects($this->any())
			->method('groupname2dn')
			->will($this->returnValue('cn=foobar,dc=foo,dc=bar'));

		$access->expects($this->once())
			->method('ownCloudUserNames')
			->will($this->returnValue(array('lisa', 'bart', 'kira', 'brad')));

		$groupBackend = new GroupLDAP($access);
		$users = $groupBackend->usersInGroup('foobar');

		$this->assertSame(4, count($users));
	}

	/**
	 * tests that a user counting is complete, if all it's members have the group
	 * as their primary.
	 */
	public function  testCountUsersInGroupPrimaryMembersOnly() {
		$access = $this->getAccessMock();
		$this->enableGroups($access);

		$access->connection->expects($this->any())
			->method('getFromCache')
			->will($this->returnValue(null));

		$access->expects($this->any())
			->method('readAttribute')
			->will($this->returnCallback(function($dn, $attr) {
				if($attr === 'primaryGroupToken') {
					return array(1337);
				}
				return array();
			}));

		$access->expects($this->any())
			->method('groupname2dn')
			->will($this->returnValue('cn=foobar,dc=foo,dc=bar'));

		$access->expects($this->once())
			->method('countUsers')
			->will($this->returnValue(4));

		$groupBackend = new GroupLDAP($access);
		$users = $groupBackend->countUsersInGroup('foobar');

		$this->assertSame(4, $users);
	}

}
