<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test\Sharing;

use DateTime;
use DateTimeImmutable;
use NCU\Sharing\ISharingManager;
use NCU\Sharing\Permission\SharePermission;
use NCU\Sharing\Recipient\ShareRecipient;
use NCU\Sharing\ShareAccessContext;
use NCU\Sharing\ShareState;
use NCU\Sharing\Source\ShareSource;
use OC\Core\Sharing\Recipient\UserShareRecipientType;
use OC\Share20\ShareAttributes;
use OC\Sharing\SharingLegacySync;
use OCA\Circles\Model\Circle;
use OCA\Files\Sharing\Permission\NodeDownloadSharePermissionType;
use OCA\Files\Sharing\Permission\NodeReadSharePermissionType;
use OCA\Files\Sharing\Source\NodeShareSourceType;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Test\TestCase;
use Test\Traits\GroupTrait;
use Test\Traits\UserTrait;

/**
 * @psalm-suppress UndefinedClass Circles -_-
 */
#[Group(name: 'DB')]
final class SharingLegacySyncTest extends TestCase {
	use UserTrait;
	use GroupTrait;

	private IRootFolder $rootFolder;

	private IUserManager $userManager;

	private SharingLegacySync $legacySync;

	private ISharingManager $sharingManager;

	private IManager $legacySharingManager;

	private IDBConnection $dbConnection;

	private IUser $owner;

	private IUser $user1;

	private IUser $user2;

	private IGroup $group;

	private ?Circle $circle = null;

	private Folder $nodeFolder;

	private File $nodeFile;

	#[\Override]
	public function setUp(): void {
		parent::setUp();

		$this->rootFolder = Server::get(IRootFolder::class);
		$this->userManager = Server::get(IUserManager::class);
		$this->legacySync = Server::get(SharingLegacySync::class);
		$this->sharingManager = Server::get(ISharingManager::class);
		$this->legacySharingManager = Server::get(IManager::class);
		$this->dbConnection = Server::get(IDBConnection::class);

		$this->owner = $this->createUser('owner', 'password');
		$this->user1 = $this->createUser('user1', 'password');
		$this->user2 = $this->createUser('user2', 'password');
		$this->group = $this->createGroup('group');

		if (class_exists(CirclesManager::class)) {
			$circlesManager = Server::get(CirclesManager::class);
			$circlesManager->startSession($circlesManager->getLocalFederatedUser($this->owner->getUID()));
			/** @psalm-suppress MixedAssignment */
			$this->circle = $circlesManager->createCircle('circle');
		}

		$ownerFolder = $this->rootFolder->getUserFolder($this->owner->getUID());
		$this->nodeFolder = $ownerFolder->newFolder('foo');
		$this->nodeFile = $ownerFolder->newFile('foo.txt');

		$this->dbConnection->beginTransaction();
	}

	#[\Override]
	public function tearDown(): void {
		foreach ($this->legacySharingManager->getAllShares() as $legacyShare) {
			$this->legacySharingManager->deleteShare($legacyShare);
		}

		$accessContext = new ShareAccessContext(overrideChecks: true);
		foreach ($this->sharingManager->getShares($accessContext, null, null, null, null, null, null) as $share) {
			$this->sharingManager->deleteShare($accessContext, $share);
		}

		// Can't rollback, because it would rollback the classmap data, but the ClassMapper still has the values cached.
		$this->dbConnection->commit();
		parent::tearDown();
	}

	public function testDeleteShare(): void {
		$this->legacySharingManager->createShare(
			$this->legacySharingManager->newShare()
				->setNode($this->nodeFolder)
				->setShareType(IShare::TYPE_USER)
				->setSharedWith($this->user1->getUID())
				->setSharedBy($this->owner->getUID())
				->setShareOwner($this->owner->getUID())
				->setPermissions(Constants::PERMISSION_READ)
				->setAttributes((new ShareAttributes())->setAttribute('permissions', 'download', true))
		);

		$shares = $this->sharingManager->getShares(new ShareAccessContext(overrideChecks: true), null, null, null, null, null, null);
		$this->assertCount(1, $shares);

		$this->sharingManager->deleteShare(new ShareAccessContext(overrideChecks: true), $shares[0]);

		$this->assertEmpty(iterator_to_array($this->legacySharingManager->getAllShares()));
	}

	public function testDeleteLegacyShare(): void {
		$legacyShare = $this->legacySharingManager->createShare(
			$this->legacySharingManager->newShare()
				->setNode($this->nodeFolder)
				->setShareType(IShare::TYPE_USER)
				->setSharedWith($this->user1->getUID())
				->setSharedBy($this->owner->getUID())
				->setShareOwner($this->owner->getUID())
				->setPermissions(Constants::PERMISSION_READ)
				->setAttributes((new ShareAttributes())->setAttribute('permissions', 'download', true))
		);

		$shares = $this->sharingManager->getShares(new ShareAccessContext(overrideChecks: true), null, null, null, null, null, null);
		$this->assertCount(1, $shares);

		$this->legacySharingManager->deleteShare($legacyShare);

		$this->assertEmpty($this->sharingManager->getShares(new ShareAccessContext(overrideChecks: true), null, null, null, null, null, null));
	}

	private function fixLegacyShare(IShare $legacyShare): IShare {
		// Having the node set seems to trigger and infinite loop in PHPUnit when comparing the objects.
		$node = $legacyShare->getNode();
		$legacyShare->setNodeId($node->getId());
		$legacyShare->setNodeType($node instanceof File ? 'file': 'folder');

		// There might be a closure that we can't reproduce and we want the value the closure returns to be set.
		$legacyShare->getSharedWithDisplayName();

		return $legacyShare;
	}

	/**
	 * @param list<IShare> $a
	 * @param list<IShare> $b
	 */
	private function assertLegacySharesEquals(array $a, array $b): void {
		$this->assertEquals(array_map($this->fixLegacyShare(...), $a), array_map($this->fixLegacyShare(...), $b));
	}

	/**
	 * Legacy shares are not able to store sub-second timestamps, so we truncate it for the tests.
	 */
	private function truncateCreationTime(DateTimeImmutable $created): DateTimeImmutable {
		return $created->setTime(
			(int)$created->format('H'),
			(int)$created->format('i'),
			(int)$created->format('s'),
		);
	}

	/**
	 * @return list<array{ShareState, int}>
	 */
	public static function dataShareState(): array {
		return [
			[ShareState::Active, 1],
			[ShareState::Draft, 0],
			[ShareState::Deleted, 0],
		];
	}

	#[DataProvider('dataShareState')]
	public function testShareStateToLegacy(ShareState $shareState, int $legacySharesCount): void {
		$accessContext = new ShareAccessContext($this->owner);
		$share = $this->sharingManager->createShare($accessContext);
		$share = $this->sharingManager->addShareSource($accessContext, $share, new ShareSource(NodeShareSourceType::class, (string)$this->nodeFolder->getId()));
		$share = $this->sharingManager->addShareRecipient($accessContext, $share, new ShareRecipient(UserShareRecipientType::class, $this->user1->getUID(), null));
		$share = $this->sharingManager->updateSharePermission($accessContext, $share, new SharePermission(NodeReadSharePermissionType::class, true));
		$share = $this->sharingManager->updateSharePermission($accessContext, $share, new SharePermission(NodeDownloadSharePermissionType::class, true));
		$this->sharingManager->updateShareState($accessContext, $share, $shareState);

		$legacyShares = array_values(iterator_to_array($this->legacySharingManager->getAllShares()));
		$this->assertCount($legacySharesCount, $legacyShares);
	}

	public function testShareStateFromLegacy(): void {
		$this->legacySharingManager->createShare(
			$this->legacySharingManager->newShare()
				->setNode($this->nodeFolder)
				->setShareType(IShare::TYPE_USER)
				->setSharedWith($this->user1->getUID())
				->setSharedBy($this->owner->getUID())
				->setShareOwner($this->owner->getUID())
				->setPermissions(Constants::PERMISSION_READ)
		);

		$shares = $this->sharingManager->getShares(new ShareAccessContext(overrideChecks: true), null, null, null, null, null, null);
		$this->assertCount(1, $shares);
		$this->assertEquals(ShareState::Active, $shares[0]->state);
	}

	public function testSingleFolderToLocalUserWithReadAndDownloadPermission(): void {
		$accessContext = new ShareAccessContext($this->owner);
		$share = $this->sharingManager->createShare($accessContext);
		$share = $this->sharingManager->addShareSource($accessContext, $share, new ShareSource(NodeShareSourceType::class, (string)$this->nodeFolder->getId()));
		$share = $this->sharingManager->addShareRecipient($accessContext, $share, new ShareRecipient(UserShareRecipientType::class, $this->user1->getUID(), null));
		$share = $this->sharingManager->updateSharePermission($accessContext, $share, new SharePermission(NodeReadSharePermissionType::class, true));
		$share = $this->sharingManager->updateSharePermission($accessContext, $share, new SharePermission(NodeDownloadSharePermissionType::class, true));
		$share = $this->sharingManager->updateShareState($accessContext, $share, ShareState::Active);

		$legacyShares = array_values(iterator_to_array($this->legacySharingManager->getAllShares()));
		$this->assertCount(1, $legacyShares);
		$this->assertLegacySharesEquals(
			[
				$this->legacySharingManager->newShare()
					->setId($legacyShares[0]->getId())
					->setProviderId('ocinternal')
					->setNode($this->nodeFolder)
					->setShareType(IShare::TYPE_USER)
					->setSharedWith($this->user1->getUID())
					->setSharedWithDisplayName($this->user1->getDisplayName())
					->setSharedBy($this->owner->getUID())
					->setShareOwner($this->owner->getUID())
					->setPermissions(Constants::PERMISSION_READ)
					->setAttributes((new ShareAttributes())->setAttribute('permissions', 'download', true))
					->setStatus(IShare::STATUS_PENDING)
					->setTarget('/' . $this->nodeFolder->getName())
					->setShareTime(DateTime::createFromImmutable($this->truncateCreationTime($share->created)))
					->setMailSend(false),
			],
			$legacyShares,
		);
	}
}
