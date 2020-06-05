<?php

declare(strict_types = 1);

/**
 * Drago Extension
 * Package built on Nette Framework
 */

namespace Drago\Authorization\Repository;

use Drago\Authorization\Entity;
use Drago\Database;


class PermissionsResourcesViewRepository extends Database\Connect
{
	use Database\Repository;

	/** @var string */
	private $table = Entity\PermissionsResourcesViewEntity::TABLE;
}
