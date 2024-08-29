<?php

/**
 * Drago Extension
 * Package built on Nette Framework
 */

declare(strict_types=1);

namespace Drago\Authorization\Control\Roles;

use App\Authorization\Control\ComponentTemplate;
use Contributte\Datagrid\Exception\DatagridException;
use Dibi\Exception;
use Dibi\Row;
use Drago\Application\UI\Alert;
use Drago\Attr\AttributeDetectionException;
use Drago\Authorization\Conf;
use Drago\Authorization\Control\Base;
use Drago\Authorization\Control\Component;
use Drago\Authorization\Control\DatagridComponent;
use Drago\Authorization\Control\Factory;
use Drago\Authorization\NotAllowedChange;
use Nette\Application\AbortException;
use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Caching\Cache;
use Nette\Forms\Controls\SelectBox;
use Nette\SmartObject;
use Throwable;


/**
 * @property-read ComponentTemplate $template
 */
class RolesControl extends Component implements Base
{
	use SmartObject;
	use Factory;

	public string $snippetFactory = 'roles';


	public function __construct(
		private readonly Cache $cache,
		private readonly RolesRepository $rolesRepository,
	) {
	}


	public function render(): void
	{
		$template = $this->createRender();
		$template->setFile($this->templateControl ?: __DIR__ . '/Roles.latte');
		$template->render();
	}


	#[Requires(ajax: true)]
	public function handleClickOpenComponent(): void
	{
		$this->offCanvasComponent();
	}


	protected function createComponentDelete(): Form
	{
		$form = $this->create();
		$form->addHidden('id', $this->id)
			->addRule($form::Integer);

		$form->addSubmit('cancel', 'Cancel')->onClick[] = function () {
			$this->redrawControl($this->snippetDeleteItem);
			if (!$this->templateControl) {
				$this->redrawControl($this->snippetFactory);
			}
		};

		$form->addSubmit('confirm', 'Confirm')->onClick[] = function (Form $form, \stdClass $data) {
			$this->rolesRepository->delete(RolesEntity::PrimaryKey, $data->id)->execute();
			$this->cache->remove(Conf::Cache);
			$this->getPresenter()->flashMessage('Role deleted.', Alert::Info);
			$this->redrawControl($this->snippetDeleteItem);
			if (!$this->templateControl) {
				$this->redrawControl($this->snippetFactory);
			}
			$this->redrawControlMessage();
			$this->closeComponent();
			$this['grid']->reload();
		};
		return $form;
	}


	/**
	 * @throws AttributeDetectionException
	 */
	protected function createComponentFactory(): Form
	{
		$form = $this->create();
		$form->addText(RolesEntity::ColumnName, 'Role')
			->setHtmlAttribute('placeholder', 'Role name')
			->setHtmlAttribute('autocomplete', 'off')
			->setRequired();

		if ($this->getSignal()) {
			foreach ($this->rolesRepository->getRoles() as $key => $item) {
				if ($this->id !== $key) {
					$roles[$key] = $item;
				}
			}
		}

		$form->addSelect(RolesEntity::ColumnParent, 'Parent', $roles ?? $this->rolesRepository->getRoles())
			->setPrompt('Select parent')
			->setRequired();

		$form->addHidden(RolesEntity::PrimaryKey)
			->addRule($form::Integer)
			->setNullable();

		$form->addSubmit('send', 'Send');
		$form->onSuccess[] = [$this, 'success'];
		return $form;
	}


	/**
	 * @throws AbortException
	 */
	public function success(Form $form, RolesData $data): void
	{
		try {
			if ($data->id !== null && $data->id < $data->parent) {
				throw new \Exception('It is not allowed to select a higher parent.', 1);
			}

			$this->rolesRepository->save($data->toArray());
			$this->cache->remove(Conf::Cache);

			$parent = $this['factory']['parent'];
			if ($parent instanceof SelectBox) {
				$parent->setItems($this->rolesRepository->getRoles());
			}

			$message = $data->id ? 'Role updated.' : 'The role was inserted.';
			$this->getPresenter()->flashMessage($message, Alert::Success);

			if ($data->id) {
				$this->closeComponent();
			}

			$this->redrawControlMessage();
			$this->redrawControl($this->snippetFactory);
			$this['grid']->reload();
			$form->reset();

		} catch (Throwable $e) {
			$message = match ($e->getCode()) {
				1 => $e->getMessage(),
				1062 => 'This role already exists.',
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
		$items = $this->rolesRepository->get($id)->record();
		$items ?: $this->error();

		try {
			if ($this->rolesRepository->isAllowed($items->name)) {
				$form = $this['factory'];
				$form->setDefaults($items);

				$buttonSend = $this->getFormComponent($form, 'send');
				$buttonSend->setCaption('Edit');
				$this->offCanvasComponent();

			}

		} catch (NotAllowedChange $e) {
			$message = match ($e->getCode()) {
				1001 => 'The role is not allowed to be updated.',
				default => 'Unknown status code.',
			};

			$this->getPresenter()->flashMessage($message, Alert::Warning);
			$this->redrawControlMessage();
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
		$items = $this->rolesRepository->get($id)->record();
		$items ?: $this->error();

		try {
			$parent = $this->rolesRepository->findParent($items->id);
			if (!$parent && $this->rolesRepository->isAllowed($items->name)) {
				$this->deleteItems = $items->name;
				$this->modalComponent();
			}

		} catch (Throwable $e) {
			$message = match ($e->getCode()) {
				1001 => 'The role is not allowed to be deleted.',
				1002 => 'The role cannot be deleted because it is bound to another role.',
				default => 'Unknown status code.',
			};

			$this->getPresenter()->flashMessage($message, Alert::Warning);
			$this->redrawControlMessage();
		}
	}


	/**
	 * @throws AttributeDetectionException
	 * @throws DataGridException
	 */
	protected function createComponentGrid($name): DatagridComponent
	{
		$grid = new DatagridComponent($this, $name);
		$grid->setDataSource($this->rolesRepository->getAll());

		if ($this->translator) {
			$grid->setTranslator($this->translator);
		}

		if ($this->templateGrid) {
			$grid->setTemplateFile($this->templateGrid);
		}

		$grid->addColumnBase('name', 'Name');
		$grid->addColumnText('parent', 'Parent')
			->setSortable()
			->setRenderer(fn(Row|RolesEntity $item) => $this->rolesRepository->findByParent($item->parent)?->name)
			->setFilterText();

		$grid->addActionEdit('edit', 'Edit');
		$grid->addActionDeleteBase('delete', 'Delete');
		return $grid;
	}
}
