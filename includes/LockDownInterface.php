<?php
namespace LatestDiscussions;

use User;
use MediaWiki\Extensions\Lockdown\Hooks as LockdownHooks;

/**
 * Lockdown interface
 *
 * iherits from Lockdown hooks to be able to call protected methods
 */
class LockDownInterface extends LockdownHooks {

	/**
	 *
	 * @param User $user
	 * @param int $ns namespace id
	 * @return boolean
	 */
	public static function userCanSeeNamespace(User $user, $ns) {
		$ugroups = $user->getEffectiveGroups();

		return self::namespaceCheck($ns, $ugroups);
	}
}