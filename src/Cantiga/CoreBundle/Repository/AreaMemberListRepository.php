<?php
/*
 * This file is part of Cantiga Project. Copyright 2015 Tomasz Jedrzejewski.
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

use Doctrine\DBAL\Connection;
use Exception;
use Cantiga\CoreBundle\CoreTables;
use Cantiga\CoreBundle\Entity\Area;
use Cantiga\CoreBundle\Entity\User;
use Cantiga\Metamodel\DataTable;
use Cantiga\Metamodel\Exception\ItemNotFoundException;
use Cantiga\Metamodel\Join;
use Cantiga\Metamodel\MembershipRoleResolver;
use Cantiga\Metamodel\QueryBuilder;
use Cantiga\Metamodel\QueryClause;
use Cantiga\Metamodel\QueryOperator;
use Cantiga\Metamodel\Transaction;

/**
 * Shows the list of members of the given area.
 *
 * @author Tomasz Jędrzejewski
 */
class AreaMemberListRepository
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
	 * @var MembershipRoleResolver
	 */
	private $roleResolver;
	
	public function __construct(Connection $conn, Transaction $transaction, MembershipRoleResolver $roleResolver)
	{
		$this->conn = $conn;
		$this->transaction = $transaction;
		$this->roleResolver = $roleResolver;
	}
	
	/**
	 * @return DataTable
	 */
	public function createDataTable()
	{
		$dt = new DataTable();
		$dt->id('id', 'i.id')
			->searchableColumn('name', 'i.name')
			->column('location', 'p.location')
			->column('note', 'm.note');
		return $dt;
	}
	
	public function listData(Area $area, DataTable $dataTable)
	{
		$qb = QueryBuilder::select()
			->field('i.id', 'id')
			->field('i.name', 'name')
			->field('p.location', 'location')
			->field('m.note', 'note')
			->from(CoreTables::USER_TBL, 'i')
			->join(CoreTables::USER_PROFILE_TBL, 'p', QueryClause::clause('p.`userId` = i.`id`'))
			->join(CoreTables::AREA_MEMBER_TBL, 'm', QueryClause::clause('m.`userId` = i.`id`'))
			->where(QueryClause::clause('m.areaId = :areaId AND i.active = 1', ':areaId', $area->getId()));	
		
		$countingQuery = QueryBuilder::select()
			->from(CoreTables::USER_TBL, 'i')
			->join(CoreTables::AREA_MEMBER_TBL, 'm', QueryClause::clause('m.`userId` = i.`id`'))
			->where(QueryClause::clause('m.areaId = :areaId AND i.active = 1', ':areaId', $area->getId()));
		
		$recordsTotal = QueryBuilder::copyWithoutFields($countingQuery)
			->field('COUNT(id)', 'cnt')
			->where($dataTable->buildCountingCondition($qb->getWhere()))
			->fetchCell($this->conn);
		$recordsFiltered = QueryBuilder::copyWithoutFields($countingQuery)
			->field('COUNT(id)', 'cnt')
			->where($dataTable->buildFetchingCondition($qb->getWhere()))
			->fetchCell($this->conn);

		$dataTable->processQuery($qb);
		return $dataTable->createAnswer(
			$recordsTotal,
			$recordsFiltered,
			$qb->where($dataTable->buildFetchingCondition($qb->getWhere()))->fetchAll($this->conn)
		);
	}
	
	/**
	 * @param $area Current area
	 * @param $id User ID
	 * @return User
	 */
	public function getItem(Area $area, $id)
	{
		$this->transaction->requestTransaction();
		try {
			$user = User::fetchLinkedProfile($this->conn, $this->roleResolver, $area,
				Join::create(CoreTables::AREA_MEMBER_TBL, 'm', QueryClause::clause('m.userId = u.id')),
				QueryOperator::op('AND')
					->expr(QueryClause::clause('m.areaId = :areaId', ':areaId', $area->getId()))
					->expr(QueryClause::clause('u.`id` = :userId', ':userId', $id)));
			
			if (false === $user) {
				throw new ItemNotFoundException('The specified user has not been found.');
			}
			return $user;
		} catch(Exception $exception) {
			$this->transaction->requestRollback();
			throw $exception;
		}
	}
}
