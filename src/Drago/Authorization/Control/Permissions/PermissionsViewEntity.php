<?php

/**
 * Drago Extension
 * Package built on Nette Framework
 */

declare(strict_types=1);

namespace Drago\Authorization\Control\Permissions;

use Drago;


/**
 * Entity representing a record in the 'permissions_view' table.
 * This entity maps the structure of the permissions view, including resource, privilege, role, and permission status.
 */
class PermissionsViewEntity extends Drago\Database\Entity
{
	/** The table name in the database */
	public const string Table = 'permissions_view';

	/** The primary key column name */
	public const string PrimaryKey = 'id';

	/** The column name for the resource */
	public const string ColumnResource = 'resource';

	/** The column name for the privilege */
	public const string ColumnPrivilege = 'privilege';

	/** The column name for the role */
	public const string ColumnRole = 'role';

	/** The column name for the permission status (allowed/denied) */
	public const string ColumnAllowed = 'allowed';

	/** The unique identifier for the permission entry */
	public int $id;

	/** The resource associated with the permission */
	public ?string $resource = null;

	/** The privilege associated with the permission */
	public ?string $privilege = null;

	/** The role associated with the permission */
	public ?string $role = null;

	/** The permission status (1 = allowed, 0 = denied) */
	public int $allowed;
}
