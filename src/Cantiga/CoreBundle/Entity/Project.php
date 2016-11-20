<?php
/*
 * This file is part of Cantiga Project. Copyright 2016 Tomasz Jedrzejewski.
 *
 * Cantiga Project is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Cantiga Project is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Foobar; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
namespace Cantiga\CoreBundle\Entity;

use Cantiga\Components\Hierarchy\Entity\Member;
use Cantiga\Components\Hierarchy\Entity\MembershipRole;
use Cantiga\Components\Hierarchy\HierarchicalInterface;
use Cantiga\Components\Hierarchy\MembershipEntityInterface;
use Cantiga\Components\Hierarchy\MembershipRoleResolverInterface;
use Cantiga\CoreBundle\Api\ExtensionPoints\ExtensionPointFilter;
use Cantiga\CoreBundle\Api\ModuleAwareInterface;
use Cantiga\CoreBundle\CoreTables;
use Cantiga\CoreBundle\Entity\Traits\EntityTrait;
use Cantiga\Metamodel\Capabilities\EditableEntityInterface;
use Cantiga\Metamodel\Capabilities\IdentifiableInterface;
use Cantiga\Metamodel\Capabilities\InsertableEntityInterface;
use Cantiga\Metamodel\DataMappers;
use Cantiga\Metamodel\Membership;
use Doctrine\DBAL\Connection;
use PDO;

class Project implements IdentifiableInterface, InsertableEntityInterface, EditableEntityInterface, MembershipEntityInterface, HierarchicalInterface
{
	use EntityTrait;
	
	private $id;
	private $name;
	private $slug;
	private $description;
	private $parentProject = null;
	private $modules = [];
	private $areasAllowed = false;
	private $areaRegistrationAllowed = false;
	private $archived = false;
	private $createdAt =  null;
	private $archivedAt = null;
	
	private $pendingArchivization = false;
	
	/**
	 * Fetches an array of ID-s of the active projects.
	 * 
	 * @param Connection $conn
	 * @return array
	 */
	public static function fetchActiveIds(Connection $conn)
	{
		$list = [];
		foreach ($conn->fetchAll('SELECT `id` FROM `'.CoreTables::PROJECT_TBL.'` WHERE `archived` = 0') as $row) {
			$list[] = $row['id'];
		}
		return $list;
	}

	public static function fetchActive(Connection $conn, $id)
	{
		$data = $conn->fetchAssoc('SELECT p.*, '
			. self::createEntityFieldList()
			. 'FROM `'.CoreTables::PROJECT_TBL.'` p '
			. self::createEntityJoin('p')
			. 'WHERE p.`id` = :id AND p.`archived` = 0', [':id' => $id]);
		if (false === $data) {
			return false;
		}
		$item = self::fromArray($data);
		$item->entity = Entity::fromArray($data, 'entity');
		return $item;
	}
	
	public static function fetchBySlug(Connection $conn, $slug)
	{
		$data = $conn->fetchAssoc('SELECT p.*, '
			. self::createEntityFieldList()
			. 'FROM `'.CoreTables::PROJECT_TBL.'` p '
			. self::createEntityJoin('p')
			. 'WHERE p.`slug` = :slug AND p.`archived` = 0', [':slug' => $slug]);
		if (false === $data) {
			return false;
		}
		$item = self::fromArray($data);
		$item->entity = Entity::fromArray($data, 'entity');
		return $item;
	}
	
	public static function fetch(Connection $conn, $id)
	{
		$data = $conn->fetchAssoc('SELECT p.*, '
			. self::createEntityFieldList()
			. 'FROM `'.CoreTables::PROJECT_TBL.'` p '
			. self::createEntityJoin('p')
			. 'WHERE p.`id` = :id', [':id' => $id]);
		if (false === $data) {
			return false;
		}
		$item = self::fromArray($data);
		$item->entity = Entity::fromArray($data, 'entity');
		return $item;
	}

	/**
	 * @param Connection $conn
	 * @param int $projectId
	 * @param int $userId
	 * @return Membership
	 */
	public static function fetchMembership(Connection $conn, MembershipRoleResolverInterface $resolver, $slug, $userId)
	{
		$data = $conn->fetchAssoc('SELECT p.*, '
			. 'm.`role` AS `membership_role`, m.`note` AS `membership_note`, '
			. self::createEntityFieldList()
			. 'FROM `'.CoreTables::PROJECT_TBL.'` p '
			. self::createEntityJoin('p')
			. 'INNER JOIN `'.CoreTables::PROJECT_MEMBER_TBL.'` m ON m.`projectId` = p.`id` '
			. 'WHERE m.`userId` = :userId AND p.`slug` = :slug', [':userId' => $userId, ':slug' => $slug]);
		if(false === $data) {
			return false;
		}
		$project = self::fromArray($data);
		$project->entity = Entity::fromArray($data, 'entity');
		$role = $resolver->getRole('Project', $data['membership_role']);
		return new Membership($project, $role, $data['membership_note']);
	}
	
	/**
	 * Finds the project of the given ID, where the area registration is currently possible. False is returned,
	 * if the project is not found.
	 * 
	 * @param Connection $conn
	 * @param int $projectId
	 * @return Project|boolean
	 */
	public static function fetchAvailableForRegistration(Connection $conn, $projectId)
	{
		$data = $conn->fetchAssoc('SELECT p.*, '
			. self::createEntityFieldList()
			. 'FROM `'.CoreTables::PROJECT_TBL.'` p '
			. self::createEntityJoin('p')
			. 'WHERE p.`id` = :id AND p.`archived` = 0 AND p.`areasAllowed` = 1 AND p.`areaRegistrationAllowed` = 1', [':id' => $projectId]);
		if (empty($data)) {
			return false;
		}
		$item = self::fromArray($data);
		$item->entity = Entity::fromArray($data, 'entity');
		return $item;
	}

	public static function fromArray($array, $prefix = '')
	{
		$item = new Project;
		DataMappers::fromArray($item, $array, $prefix);
		if (is_string($item->modules)) {
			if ($item->modules === '') {
				$item->modules = [];
			} else {
				$item->modules = explode(',', $item->modules);
			}
		}
		return $item;
	}
	
	public static function getRelationships()
	{
		return ['parentProject'];
	}
	
	public function getTypeName():string
	{
		return 'Project';
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	public function setId($id)
	{
		DataMappers::noOverwritingId($this->id);
		$this->id = $id;
		return $this;
	}
	
	public function getName()
	{
		return $this->name;
	}

	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}
	
	public function getSlug()
	{
		return $this->slug;
	}

	public function setSlug($slug)
	{
		$this->slug = $slug;
		return $this;
	}

	public function getDescription()
	{
		return $this->description;
	}

	public function getParentProject()
	{
		return $this->parentProject;
	}

	public function getModules()
	{
		return $this->modules;
	}

	public function getAreasAllowed()
	{
		return $this->areasAllowed;
	}
	
	public function getAreaRegistrationAllowed()
	{
		return $this->areaRegistrationAllowed;
	}

	public function getArchived()
	{
		return $this->archived;
	}

	public function getCreatedAt()
	{
		return $this->createdAt;
	}

	public function getArchivedAt()
	{
		return $this->archivedAt;
	}

	public function setDescription($description)
	{
		$this->description = $description;
		return $this;
	}

	public function setParentProject($parentProject)
	{
		$this->parentProject = $parentProject;
		return $this;
	}

	public function setModules($modules)
	{
		$this->modules = $modules;
		return $this;
	}

	public function setAreasAllowed($areasAllowed)
	{
		$this->areasAllowed = $areasAllowed;
		return $this;
	}

	public function setAreaRegistrationAllowed($areaRegistrationAllowed)
	{
		$this->areaRegistrationAllowed = $areaRegistrationAllowed;
		return $this;
	}
	
	public function setArchived($archived)
	{
		$this->archived = $archived;
		return $this;
	}

	public function setCreatedAt($createdAt)
	{
		$this->createdAt = $createdAt;
		return $this;
	}

	public function setArchivedAt($archivedAt)
	{
		$this->archivedAt = $archivedAt;
		return $this;
	}
	
	/**
	 * Checks whether the given module-aware item is supported, according to the module
	 * configuration of this project.
	 * 
	 * @param ModuleAwareInterface $moduleAware Item to check
	 * @return boolean
	 */
	public function supports(ModuleAwareInterface $moduleAware)
	{
		return $this->supportsModule($moduleAware->getModule());
	}
	
	/**
	 * Checks whether this project supports the given module.
	 * 
	 * @param string $moduleName
	 */
	public function supportsModule($moduleName): bool
	{
		if ($moduleName == 'core') {
			return true;
		}
		return in_array($moduleName, $this->modules);
	}
	
	/**
	 * Builds an extension point filter that limits the available implementations to those
	 * ones which belong to the modules activated for this project.
	 */
	public function createExtensionPointFilter(): ExtensionPointFilter
	{
		$modules = $this->modules;
		$modules[] = 'core';
		return new ExtensionPointFilter($modules);
	}
	
	/**
	 * Sends the project to archive. Additional actions from other modules should be performed by attaching
	 * to <tt>CantigaEvents::PROJECT_ARCHIVIZED</tt> event. To check whether the archivization has been called
	 * on the entity, call {@link #isPendingArchivization()}.
	 */
	public function archivize()
	{
		if (!$this->archived) {
			$this->archivedAt = time();
			$this->archived = true;
			$this->pendingArchivization = true;
		}
	}
	
	/**
	 * Informs that the archivization operation has been performed on this project.
	 */
	public function isPendingArchivization(): bool
	{
		return $this->pendingArchivization;
	}
	
	public function insert(Connection $conn)
	{
		$this->slug = DataMappers::generateSlug($conn, CoreTables::PROJECT_TBL);
		
		$this->entity = new Entity();
		$this->entity->setType('Project');
		$this->entity->setName($this->name);
		$this->entity->setSlug($this->slug);
		$this->entity->insert($conn);
		
		$conn->insert(
			CoreTables::PROJECT_TBL,
			DataMappers::pick($this, ['name', 'slug', 'description', 'parentProject', 'areasAllowed', 'areaRegistrationAllowed', 'entity'], [
				'modules' => implode(',', $this->getModules()),
				'archived' => false,
				'createdAt' => time(),
				'archivedAt' => null,
			])
		);
		return $this->id = $conn->lastInsertId();
	}

	public function update(Connection $conn)
	{
		$this->entity->setName($this->name);
		$this->entity->update($conn);
		
		return $conn->update(
			CoreTables::PROJECT_TBL,
			DataMappers::pick($this, ['name', 'description', 'parentProject', 'areasAllowed', 'areaRegistrationAllowed', 'archived', 'archivedAt'], [
				'modules' => implode(',', $this->getModules())
			]),
			DataMappers::pick($this, ['id'])
		);
	}
	
	/**
	 * Finds the hints for the users that could join the project, basing on their partial e-mail
	 * address.
	 * 
	 * @param string $mailQuery
	 * @return array
	 */
	public function findHints(Connection $conn, $mailQuery)
	{
		$mailQuery = trim(str_replace('%', '', $mailQuery));
		if (strlen($mailQuery) < 3) {
			return array();
		}
		
		$items = $conn->fetchAll('SELECT `email` FROM `'.CoreTables::USER_TBL.'` WHERE '
			. '`email` LIKE :email AND `id` NOT IN(SELECT `userId` FROM `'.CoreTables::PROJECT_MEMBER_TBL.'` WHERE `projectId` = :project) AND `active` = 1 AND `removed` = 0 ORDER BY `email` DESC LIMIT 15', [':project' => $this->getId(), ':email' => $mailQuery.'%']);
		if (!empty($items)) {
			$result = array();
			foreach ($items as $item) {
				$result[] = $item['email'];
			}
			return $result;
		}
		return array();
	}
	
	public function findMembers(Connection $conn, MembershipRoleResolverInterface $roleResolver): array
	{
		$stmt = $conn->prepare('SELECT i.`id`, i.`name`, i.`avatar`, i.`lastVisit`, p.`location`, c.`email` AS `contactMail`, '
			. 'c.`telephone` AS `contactTelephone`, c.`notes` AS `notes`, m.`role` AS `membershipRole`, m.`note` AS `membershipNote` '
			. 'FROM `'.CoreTables::USER_TBL.'` i '
			. 'INNER JOIN `'.CoreTables::USER_PROFILE_TBL.'` p ON p.`userId` = i.`id` '
			. 'INNER JOIN `'.CoreTables::PROJECT_MEMBER_TBL.'` m ON m.`userId` = i.`id` '
			. 'LEFT JOIN `'.CoreTables::CONTACT_TBL.'` c ON c.`userId` = i.`id` AND c.`projectId` = :projectId '
			. 'WHERE m.`projectId` = :entityId AND i.`active` = 1 AND i.`removed` = 0 '
			. 'ORDER BY i.`name`');
		$stmt->bindValue(':projectId', $this->getId());
		$stmt->bindValue(':entityId', $this->getId());
		$stmt->execute();
		$results = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$results[] = new Member(
				$row, new Membership($this, $roleResolver->getRole('Project', $row['membershipRole']), $row['membershipNote'])
			);
		}
		$stmt->closeCursor();
		return $results;
	}
	
	public function findMember(Connection $conn, MembershipRoleResolverInterface $resolver, int $id)
	{
		$stmt = $conn->prepare('SELECT i.`id`, i.`name`, i.`avatar`, i.`lastVisit`, p.`location`, c.`email` AS `contactMail`, '
			. 'c.`telephone` AS `contactTelephone`, c.`notes` AS `notes`, m.`role` AS `membershipRole`, m.`note` AS `membershipNote` '
			. 'FROM `'.CoreTables::USER_TBL.'` i '
			. 'INNER JOIN `'.CoreTables::USER_PROFILE_TBL.'` p ON p.`userId` = i.`id` '
			. 'INNER JOIN `'.CoreTables::PROJECT_MEMBER_TBL.'` m ON m.`userId` = i.`id` '
			. 'LEFT JOIN `'.CoreTables::CONTACT_TBL.'` c ON c.`userId` = i.`id` AND c.`projectId` = :projectId '
			. 'WHERE m.`projectId` = :entityId AND i.`active` = 1 AND i.`removed` = 0 AND i.`id` = :userId '
			. 'ORDER BY i.`name`');
		$stmt->bindValue(':projectId', $this->getId());
		$stmt->bindValue(':entityId', $this->getId());
		$stmt->bindValue(':userId', $id);
		$stmt->execute();
		$results = [];
		if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$stmt->closeCursor();
			return new Member(
				$row, new Membership($this, $resolver->getRole('Project', $row['membershipRole']), $row['membershipNote'])
			);
		}
		$stmt->closeCursor();
		return false;
	}

	public function joinMember(Connection $conn, User $user, MembershipRole $role, $note)
	{
		$ifExists = $conn->fetchColumn('SELECT `userId` FROM `'.CoreTables::PROJECT_MEMBER_TBL.'` WHERE `projectId` = :project AND `userId` = :user', [':project' => $this->getId(), ':user' => $user->getId()]);
		if (false === $ifExists) {
			$conn->insert(CoreTables::PROJECT_MEMBER_TBL, ['projectId' => $this->getId(), 'userId' => $user->getId(), 'role' => $role->getId(), 'note' => $note]);
			$conn->executeQuery('UPDATE `'.CoreTables::PROJECT_TBL.'` SET `memberNum` = (`memberNum` + 1) WHERE `id` = :id', [':id' => $this->id]);
			$conn->executeQuery('UPDATE `'.CoreTables::USER_TBL.'` SET `projectNum` = (`projectNum` + 1) WHERE `id` = :id', [':id' => $user->getId()]);
			return true;
		}
		return false;
	}
	
	public function editMember(Connection $conn, User $user, MembershipRole $role, $note)
	{
		return 1 == $conn->update(CoreTables::PROJECT_MEMBER_TBL, ['role' => (int) $role->getId(), 'note' => $note], ['projectId' => $this->getId(), 'userId' => $user->getId()]);
	}

	public function removeMember(Connection $conn, User $user)
	{
		if (1 == $conn->delete(CoreTables::PROJECT_MEMBER_TBL, ['projectId' => $this->getId(), 'userId' => $user->getId()])) {
			$conn->executeQuery('UPDATE `'.CoreTables::PROJECT_TBL.'` SET `memberNum` = (`memberNum` - 1) WHERE `id` = :id', [':id' => $this->id]);
			$conn->executeQuery('UPDATE `'.CoreTables::USER_TBL.'` SET `projectNum` = (`projectNum` - 1) WHERE `id` = :id', [':id' => $user->getId()]);
			return true;
		}
		return false;
	}

	public function getElementOfType(int $type)
	{
		if ($type == HierarchicalInterface::TYPE_PROJECT) {
			return $this;
		}
		return null;
	}

	public function getParents(): array
	{
		return [];
	}

	public function getRootElement(): HierarchicalInterface
	{
		return $this;
	}

}