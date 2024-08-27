<?php

/**
 * Drago Extension
 * Package built on Nette Framework
 */

declare(strict_types=1);

namespace Drago\Authorization\Control\Permissions;

use App\Authorization\Control\ComponentTemplate;
use Contributte\Datagrid\Column\Action\Confirmation\StringConfirmation;
use Contributte\Datagrid\Datagrid;
use Contributte\Datagrid\Exception\DatagridColumnStatusException;
use Contributte\Datagrid\Exception\DatagridException;
use Dibi\Exception;
use Drago\Application\UI\Alert;
use Drago\Attr\AttributeDetectionException;
use Drago\Authorization\Conf;
use Drago\Authorization\Control\Base;
use Drago\Authorization\Control\Component;
use Drago\Authorization\Control\Factory;
use Drago\Authorization\Control\Privileges\PrivilegesEntity;
use Drago\Authorization\Control\Privileges\PrivilegesRepository;
use Drago\Authorization\Control\Resources\ResourcesEntity;
use Drago\Authorization\Control\Resources\ResourcesRepository;
use Drago\Authorization\Control\Roles\RolesEntity;
use Drago\Authorization\Control\Roles\RolesRepository;
use Nette\Application\AbortException;
use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Nette\SmartObject;
use Throwable;


/**
 * @property-read ComponentTemplate $template;
 */
class PermissionsControl extends Component implements Base
{
	use SmartObject;
	use Factory;

	public string $snippetFactory = 'permissions';


	public function __construct(
		private readonly Cache $cache,
		private readonly RolesRepository $rolesRepository,
		private readonly ResourcesRepository $resourcesRepository,
		private readonly PrivilegesRepository $privilegesRepository,
		private readonly PermissionsRepository $permissionsRepository,
		private readonly PermissionsViewRepository $permissionsViewRepository,
		private readonly PermissionsRolesViewRepository $permissionsRolesViewRepository,
	) {
	}


	public function render(): void
	{
		$template = $this->createRender();
		$template->setFile($this->templateControl ?: __DIR__ . '/Permissions.latte');
		$template->render();
	}


	#[Requires(ajax: true)]
	public function handleClickOpenComponent(): void
	{
		$this->offCanvasComponent();
	}


	/**
	 * @throws AttributeDetectionException
	 */
	protected function createComponentFactory(): Form
	{
		$form = $this->create();
		$roles = $this->rolesRepository->read('*')
			->where(RolesEntity::ColumnName, '!= ?', Conf::RoleAdmin)
			->fetchPairs(RolesEntity::PrimaryKey, RolesEntity::ColumnName);

		$form->addSelect(PermissionsEntity::ColumnRoleId, 'Role', $roles)
			->setPrompt('Select role')
			->setRequired();

		$resources = $this->resourcesRepository->read('*')
			->fetchPairs(ResourcesEntity::PrimaryKey, ResourcesEntity::ColumnName);

		$form->addSelect(PermissionsEntity::ColumnResourceId, 'Resource', $resources)
			->setPrompt('Select resource')
			->setRequired();

		$privileges = $this->privilegesRepository->read('*')
			->fetchPairs(PrivilegesEntity::PrimaryKey, PrivilegesEntity::ColumnName);

		$form->addSelect(PermissionsEntity::ColumnPrivilegeId, 'Actions and signals', $privileges)
			->setPrompt('Select privilege')
			->setRequired();

		$permission = [
			'Deny',
			'Allow',
		];

		$form->addSelect(PermissionsEntity::ColumnAllowed, 'Permission', $permission)
			->setPrompt('Select permission')
			->setRequired();

		$form->addHidden(PermissionsEntity::PrimaryKey)
			->addRule($form::Integer)
			->setNullable();

		$form->addSubmit('send', 'Send');
		$form->onSuccess[] = [$this, 'success'];
		return $form;
	}


	/**
	 * @throws AbortException
	 */
	#[Requires(ajax: true)]
	public function success(Form $form, PermissionsData $data): void
	{
		try {
			$this->permissionsRepository->save($data->toArray());
			$this->cache->remove(Conf::Cache);

			$message = $data->id ? 'Permission was updated.' : 'Permission added.';
			$this->getPresenter()->flashMessage($message, Alert::Info);

			if ($data->id) {
				$this->getPresenter()->payload->close = 'close';
			}

			$this->redrawControlMessage();
			$this->redrawControl($this->snippetFactory);
			$this['grid']->reload();
			$form->reset();

		} catch (Throwable $e) {
			$message = match ($e->getCode()) {
				1062 => 'This permission is already granted.',
				default => 'Unknown status code.',
			};

			$form->addError($message);
			$this->redrawControl($this->snippetFactory);
		}
	}


	/**
	 * @throws AbortException
	 * @throws AttributeDetectionException
	 * @throws BadRequestException
	 * @throws Exception
	 */
	#[Requires(ajax: true)]
	public function handleEdit(int $id): void
	{
		$items = $this->permissionsRepository->get($id)->record();
		$items ?: $this->error();

		if ($this->getSignal()) {
			$form = $this['factory'];
			$form->setDefaults($items);

			$buttonSend = $this->getFormComponent($form, 'send');
			$buttonSend->setCaption('Edit');
			$this->offCanvasComponent();
		}
	}


	/**
	 * @throws AbortException
	 * @throws AttributeDetectionException
	 * @throws BadRequestException
	 * @throws Exception
	 */
	#[Requires(ajax: true)]
	public function handleDelete(int $id): void
	{
		$items = $this->permissionsRepository->get($id)->record();
		$items ?: $this->error();

		$this->permissionsRepository->delete(PermissionsEntity::PrimaryKey, $items->id)->execute();
		$this->cache->remove(Conf::Cache);
		$this->getPresenter()->flashMessage(
			'Permission removed.',
			Alert::Danger,
		);

		$this->redrawControlMessage();
		$this['grid']->reload();
	}


	/**
	 * @throws Exception
	 * @throws AttributeDetectionException
	 */
	public function statusChange(string $id, string $value): void
	{
		$id = (int) $id;
		$value = (int) $value;

		if ($id && $value >= 0) {
			$entity = new PermissionsEntity;
			$entity->id = $id;
			$entity->allowed = $value;

			$this->permissionsRepository->save($entity);
			$message = 'Authorization has been changed.';
			$this->getPresenter()->flashMessage($message, Alert::Info);

			if ($this->isAjax()) {
				$this->getPresenter()->redrawControl($this->snippetMessage);
				$this['grid']->reload();
			}
		}
	}


	/**
	 * @throws AttributeDetectionException
	 * @throws DataGridException
	 * @throws DataGridColumnStatusException
	 */
	protected function createComponentGrid($name): DataGrid
	{
		$grid = new DataGrid($this, $name);
		$grid->setDataSource($this->permissionsViewRepository->getAll());

		if ($this->translator) {
			$grid->setTranslator($this->translator);
		}

		if ($this->templateGrid) {
			$grid->setTemplateFile($this->templateGrid);
		}

		$roles = $this->rolesRepository->getRolesPairs();
		$grid->addColumnText('role', 'Role')
			->setSortable()
			->setFilterSelect(array_merge([
				null => $this->translator
					? $this->translate('All')
					: 'All',
			], $roles));

		$grid->addColumnText('resource', 'Resources')
			->setSortable()
			->setFilterText();

		$grid->addColumnText('privilege', 'Privileges')
			->setSortable()
			->setFilterText();

		$expirationCol = $grid->addColumnStatus('allowed', 'Permission')
			->setSortable();

		$expirationCol->setCaret(false)
			->addOption(0, 'Denied')
			->setClass('btn-warning')
			->endOption()
			->addOption(1, 'Allowed')
			->setClass('btn-success')
			->endOption()
			->setFilterSelect([
				null => $this->translator ? $this->translate('All') : 'All',
				0  => $this->translator ? $this->translate('Denied') : 'Denied',
				1  => $this->translator ? $this->translate('Allowed') : 'Allowed',
			]);
		$expirationCol->onChange[] = [$this, 'statusChange'];

		$grid->addAction('edit', 'Edit')
			->setClass('btn btn-xs btn-primary text-white ajax');

		$confirm = 'Are you sure you want to delete the selected item?';
		if ($this->translator) {
			$confirm = $this->translate($confirm);
		}
		$grid->addAction('delete', 'Delete')
			->setClass('btn btn-xs btn-danger ajax')
			->setConfirmation(new StringConfirmation($confirm));

		return $grid;
	}


	private function translate(string $name): string
	{
		return $this->translator->translate($name);
	}
}
