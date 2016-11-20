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
declare(strict_types=1);
namespace Cantiga\Components\Hierarchy\Entity;

use Cantiga\Metamodel\Membership;

/**
 * Member of the same entity, as us. We can see his/her contact information.
 */
class Member extends AbstractProfileView
{
	/**
	 * @var Membership
	 */
	private $membership;
	
	public function __construct(array $data, Membership $membership)
	{
		parent::__construct($data);
		$this->membership = $membership;
	}
	
	public function getMembership(): Membership
	{
		return $this->membership;
	}
	
	public function canViewContactInformation(Membership $viewingMember): bool
	{
		return true;
	}
	
	public function asArray(): array
	{
		$array = parent::asArray();
		$array['role'] = $this->membership->getRole()->getId();
		$array['roleName'] = $this->membership->getRole()->getName();
		$array['note'] = $this->membership->getNote() ?? '';
		return $array;
	}
	
	public static function collectionAsArray(array $memberItems): array
	{
		$result = [];
		foreach ($memberItems as $item) {
			$result[] = $item->asArray();
		}
		return $result;
	}
}
