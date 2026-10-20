<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Lookup;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;

/**
 * List, add, edit and delete screens for one table.
 *
 * Each admin screen extends this and describes its fields. Field settings:
 *   label     Text shown next to the input
 *   type      text, textarea, email, date, time, number, decimal, money,
 *             select, checkbox, password
 *   required  Shows a required marker (the model does the real validation)
 *   options   For select: a Lookup method name (e.g. 'leagues') or id => label
 *   default   Value for a new record; 'currentLeague' uses the current league
 *   help      Hint shown under the input
 *   readonly  Shown on the form but never saved from it (e.g. last login)
 *   compact   Lay out with neighbouring fields of the same compact group in a
 *             grid (true, or a group name such as 'holes')
 *
 * money fields are stored in cents and shown in dollars. password fields are
 * not table columns; the screen handles them in afterSave().
 */
abstract class CrudController extends BaseController
{
    /** @var class-string<Model> */
    protected string $modelClass;

    protected string $title;
    protected string $singular;
    protected string $baseRoute;

    /** @var list<string> Field names shown as columns on the list page */
    protected array $listColumns = [];

    /** @var list<string> Fields the list can be filtered by (select fields) */
    protected array $filters = [];

    /** @var array<string, string> Column => ASC|DESC for the list order */
    protected array $orderBy = ['id' => 'ASC'];

    /** Extra view shown under the list (e.g. the tee time shift form) */
    protected ?string $extraIndexView = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    abstract protected function fields(): array;

    public function index(): string
    {
        $model  = $this->newModel();
        $fields = $this->resolvedFields();
        $active = [];

        foreach ($this->filters as $name) {
            $value = $this->request->getGet($name);
            if ($value === null && ($this->fields()[$name]['default'] ?? null) === 'currentLeague') {
                $value = (string) Lookup::currentLeagueId();
            }
            if ($value !== null && $value !== '') {
                $model->where($name, $value);
                $active[$name] = $value;
            }
        }
        foreach ($this->orderBy as $column => $direction) {
            $model->orderBy($column, $direction);
        }

        return view('admin/crud/index', $this->viewData([
            'fields'         => $fields,
            'rows'           => $model->findAll(),
            'filterValues'   => $active,
            'extraIndexView' => $this->extraIndexView,
        ]));
    }

    public function new(): string
    {
        $record = [];
        foreach ($this->fields() as $name => $field) {
            $default = $field['default'] ?? null;
            $record[$name] = $default === 'currentLeague' ? Lookup::currentLeagueId() : $default;
        }

        return $this->form($record, null);
    }

    public function create()
    {
        $data  = $this->collect();
        $model = $this->newModel();

        $error = $this->checkBeforeSave($data, null);
        if ($error === null) {
            $id = $model->insert($this->columnsOnly($data));
            if ($id !== false) {
                $this->afterSave((int) $id, $data);

                return redirect()->to($this->baseRoute)->with('message', "{$this->singular} added.");
            }
        }

        return $this->form($data, null, $error ? ['_' => $error] : $model->errors());
    }

    public function edit(int $id): string
    {
        return $this->form($this->findOr404($id), $id);
    }

    public function update(int $id)
    {
        $this->findOr404($id);
        $data  = $this->collect();
        $model = $this->newModel();

        $error = $this->checkBeforeSave($data, $id);
        if ($error === null) {
            // The id is included so "unique" rules can skip this record.
            if ($model->update($id, ['id' => $id] + $this->columnsOnly($data))) {
                $this->afterSave($id, $data);

                return redirect()->to($this->baseRoute)->with('message', "{$this->singular} saved.");
            }
        }

        return $this->form($data, $id, $error ? ['_' => $error] : $model->errors());
    }

    public function delete(int $id)
    {
        $this->findOr404($id);

        try {
            $this->newModel()->delete($id);
        } catch (DatabaseException) {
            $hint = array_key_exists('active', $this->fields()) ? ' Mark it inactive instead.' : '';

            return redirect()->to($this->baseRoute)
                ->with('error', "This {$this->singular} is in use by other records, so it can't be deleted.{$hint}");
        }

        return redirect()->to($this->baseRoute)->with('message', "{$this->singular} deleted.");
    }

    /**
     * Return an error message to stop the save, or null to continue.
     */
    protected function checkBeforeSave(array $data, ?int $id): ?string
    {
        return null;
    }

    /**
     * Runs after a successful insert or update, with everything that was posted.
     */
    protected function afterSave(int $id, array $data): void
    {
    }

    protected function newModel(): Model
    {
        return new $this->modelClass();
    }

    protected function findOr404(int $id): array
    {
        $row = $this->newModel()->find($id);
        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $row;
    }

    protected function form(array $record, ?int $id, array $errors = []): string
    {
        return view('admin/crud/form', $this->viewData([
            'fields' => $this->resolvedFields(),
            'record' => $record,
            'id'     => $id,
            'errors' => $errors,
        ]));
    }

    protected function viewData(array $data): array
    {
        return $data + [
            'title'     => $this->title,
            'singular'  => $this->singular,
            'baseRoute' => $this->baseRoute,
            'columns'   => $this->listColumns,
            'filters'   => $this->filters,
            'actions'   => $this->rowActions(),
        ];
    }

    /**
     * Extra links on each list row: label => route with {id}.
     *
     * @return array<string, string>
     */
    protected function rowActions(): array
    {
        return [];
    }

    /**
     * Posted values for this screen's fields, converted for storage:
     * blank becomes NULL, checkboxes become 0/1 and dollars become cents.
     */
    protected function collect(): array
    {
        $data = [];
        foreach ($this->fields() as $name => $field) {
            if (! empty($field['readonly'])) {
                continue;
            }
            $value = $this->request->getPost($name);
            $type  = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $data[$name] = $value ? 1 : 0;
            } elseif ($value === null || trim((string) $value) === '') {
                $data[$name] = null;
            } elseif ($type === 'money') {
                $data[$name] = is_numeric($value) ? (int) round((float) $value * 100) : $value;
            } else {
                $data[$name] = is_string($value) ? trim($value) : $value;
            }
        }

        return $data;
    }

    /**
     * Drops values for fields that are not table columns (password fields).
     */
    protected function columnsOnly(array $data): array
    {
        foreach ($this->fields() as $name => $field) {
            if (($field['type'] ?? 'text') === 'password') {
                unset($data[$name]);
            }
        }

        return $data;
    }

    /**
     * Fields with select options loaded.
     */
    protected function resolvedFields(): array
    {
        $fields = $this->fields();
        foreach ($fields as &$field) {
            if (isset($field['options']) && is_string($field['options'])) {
                $field['options'] = Lookup::{$field['options']}();
            }
        }

        return $fields;
    }
}
