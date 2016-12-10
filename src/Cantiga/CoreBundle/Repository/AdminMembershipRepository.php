<?php
/*
 * This file is part of Cantiga Project. Copyright 2016 Cantiga contributors.
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
namespace Cantiga\CoreBundle\Repository;

use Cantiga\Components\Hierarchy\Entity\Member;
use Cantiga\Components\Hierarchy\Entity\MembershipRole;
use Cantiga\Components\Hierarchy\MembershipEntityInterface;
use Cantiga\Components\Hierarchy\MembershipRepositoryInterface;
use Cantiga\Components\Hierarchy\MembershipRoleResolverInterface;
use Cantiga\Components\Hierarchy\User\CantigaUserRefInterface;
use Cantiga\CoreBundle\CoreTables;
use Cantiga\CoreBundle\Entity\Invitation;
use Cantiga\CoreBundle\Entity\Project;
use Cantiga\CoreBundle\Entity\User;
use Cantiga\Metamodel\Exception\ItemNotFoundException;
use Cantiga\Metamodel\Exception\ModelException;
use Cantiga\Metamodel\QueryClause;
use Cantiga\Metamodel\Transaction;
use Doctrine\DBAL\Connection;
use Exception;

/**
 * Manages the project membership information.
 */
class AdminMembershipRepository implements MembershipRepositoryInterface
{
	/**
	 * @var Connection 
	 */
	private $conn;
	/**
	 * @var Transaction
	 */
	private $transaction;
	/**
	 * @var MembershipRoleResolverInterface
	 */
	private $roleResolver;
	
	public function __construct(Connection $conn, Transaction $transaction, MembershipRoleResolverInterface $roleResolver)
	{
		$this->conn = $conn;
		$this->transaction = $transaction;
		$this->roleResolver = $roleResolver;
	}
	
	public function findActiveProjects()
	{
		return $this->conn->fetchAll('SELECT id, name FROM `'.CoreTables::PROJECT_TBL.'` WHERE `archived` = 0 ORDER BY name');
	}
	
	public function getProject($projectId)
	{
		if (!ctype_digit($projectId)) {
			throw new ModelException('Invalid project ID');
		}
		
		$this->transaction->requestTransaction();
		$project = Project::fetchActive($this->conn, $projectId);
		if (empty($project)) {
			throw new ItemNotFoundException('The specified project has not been found.');
		}
		return $project;
	}
	
	public function getUserByEmail($email)
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new ModelException('Invalid e-mail address');
		}
		$this->transaction->requestTransaction();
		$user = User::fetchByCriteria($this->conn, QueryClause::clause('u.`email` = :email', ':email', $email));
		if (empty($user)) {
			throw new ItemNotFoundException('The specified user has not been found.');
		}
		return $user;
	}
	
	public function getMember(MembershipEntityInterface $place, $id)
	{
		if (!ctype_digit($id)) {
			throw new ModelException('Invalid user ID');
		}
		
		$this->transaction->requestTransaction();
		$user = $place->findMember($this->conn, $this->roleResolver, $id);
		if (empty($user)) {
			throw new ItemNotFoundException('The specified user has not been found.');
		}
		return $user;
	}
	
	public function getRole($id)
	{
		if (!ctype_digit($id)) {
			throw new ModelException('Invalid role ID');
		}
		
		return $this->roleResolver->getRole('Project', $id);
	}

	public function findHints(MembershipEntityInterface $place, $query)
	{
		$this->transaction->requestTransaction();
		return $place->findHints($this->conn, $query);
	}
	
	public function findMembers(MembershipEntityInterface $project)
	{
		$this->transaction->requestTransaction();
		return Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver));
	}

	public function joinMember(MembershipEntityInterface $project, CantigaUserRefInterface $user, MembershipRole $role, $note)
	{
		if($role->isUnknown()) {
			return ['status' => 0];
		}
		$this->transaction->requestTransaction();
		try {
			if ($project->joinMember($this->conn, $user, $role, $note, true)) {
				return ['status' => 1, 'data' => Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver))];
			}
			return ['status' => 0, 'data' => Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver))];
		} catch(Exception $exception) {
			$this->transaction->requestRollback();
			throw $exception;
		}
	}
	
	public function editMember(MembershipEntityInterface $project, CantigaUserRefInterface $user, MembershipRole $role, $note)
	{
		if($role->isUnknown()) {
			return ['status' => 0];
		}
		$this->transaction->requestTransaction();
		try {
			if ($project->editMember($this->conn, $user, $role, $note, true)) {
				return ['status' => 1, 'data' => Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver))];
			}
			return ['status' => 0, 'data' => Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver))];
		} catch(Exception $exception) {
			$this->transaction->requestRollback();
			throw $exception;
		}
	}

	public function removeMember(MembershipEntityInterface $project, CantigaUserRefInterface $user)
	{
		$this->transaction->requestTransaction();
		try {
			if ($project->removeMember($this->conn, $user)) {
				return ['status' => 1, 'data' => Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver))];
			}
			return ['status' => 0, 'data' => Member::collectionAsArray($project->findMembers($this->conn, $this->roleResolver))];
		} catch(Exception $exception) {
			$this->transaction->requestRollback();
			throw $exception;
		}
	}

	public function acceptInvitation(Invitation $invitation)
	{
	}

	public function clearMembership(User $user)
	{
		return null;
	}

}
