<?php
namespace EMedia\Formation\Builder;

use Carbon\Carbon;
use ElegantMedia\PHPToolkit\Text;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Formation
{

	protected $fields;
	protected $model;

	use BuildsFormElements;
	use BuildsHtml;

	public function __construct(Model $entity = null)
	{
		$this->fields = new Collection();
		if ($entity) {
			$this->setModel($entity);
		}
	}

	public function addField($name, $type, $order = null)
	{

	}

	/*
	 * This generates the following horizontal layout
	 *
	 * <div class="form-group row">
            <label for="" class="col-sm-4 control-label">First Name</label>
            <div class="col-sm-8">
                <input type="" class="form-control" name="name" value="{{ $entity->first_name }}">
            </div>
        </div>
	 *
	 */
	public function render($fieldName = null, $exceptFields = [], $overrides = [])
	{
		$labelLayoutClass = 'col-sm-4';
		$fieldLayoutClass = 'col-sm-8';

		$renderedContent = '';

		$user = auth()->user();

		$fields = $this->fields;
		if (is_countable($exceptFields) && count($exceptFields)) {
			$fields = $fields->filter(function ($item) use ($exceptFields) {
				if (isset($item['name']) && !in_array($item['name'], $exceptFields)) {
					return $item;
				}
			});
		}

		foreach($fields as $field) {

			// if a fieldname is given, only render that one
			if ($fieldName && $fieldName !== $field['name']) {
				continue;
			}

			// merge any overrides
			if (is_countable($overrides) && count($overrides)) {
				$field = array_merge($field, $overrides);
			}

            //check form session after post or put request
            if($field['type'] != 'password'){
                if(!empty(app('request')->old($field['name'], null))){
                    $field['value'] = app('request')->old($field['name'], null);
                }
            }

			// check user permissions
			// if the user doesn't have the given role, don't show the field
			if (isset($field['roles'])) {
				$allowedRoles = $field['roles'];
				if (!$user->isA($allowedRoles)) {
					continue;
				}
			}

			$label = $this->label($field['name'], $field['display_name'], [
				'class' => $labelLayoutClass . ' control-label',
			]);

			// TODO: add vertical layouts

			// add the field
			// TODO: add other field types
			$options = [];

			if (! empty($field['attributes'])) {
				$options = array_merge($options, is_array($field['attributes']) ? $field['attributes'] : [$field['attributes']]);
			}

			$options['class'] = 'form-control';

			if (!empty($field['class'])) $options['class'] .= ' ' . $field['class'];

			if (!empty($field['placeholder'])) $options['placeholder'] = $field['placeholder'];

			if ($field['type'] === 'text') {

				$fieldObj = $this->input('text', $field['name'], $field['value'], $options);

			} else if ($field['type'] === 'password') {

				// don't show the password as the output
				$fieldObj = $this->input('password', $field['name'], '', $options);

			} else if ($field['type'] === 'textarea') {

				$fieldObj = $this->textarea($field['name'], $field['value'], $options);

			} else if ($field['type'] === 'date') {

				// get the additional data attributes from the Model
				// add them to HTML
				// read the HTML from JS
				// update widget options

				$options['class'] .= ' ' . 'js-datepicker';
				$options['data-date-format'] = 'DD/MMM/YYYY';

				// TODO: get the format from config, if it exists
				$dateFormat = 'd/M/Y';

				$inputDate = '';
				if ($field['value'] instanceof Carbon) {
					$inputDate = $options['data-default-date'] = $field['value']->format($dateFormat);
				} elseif (empty($field['value'])) {
					// if the input is null or empty, then this field must be left blank
				} else {
					try {
						// Note from Shane:
						// the conversion below is not necessary,
						// because it's taking a string -> convert to a date -> convert back to a string
						// this will fail with an exception of the date is given in an unknown format
						$inputDate = (new Carbon($field['value']))->format($dateFormat);
					} catch (\Exception $ex) {
						$inputDate = $field['value'];
					}
				}

				// set min/max dates
				if (!empty($field['data'])) {
					// set min date
					if (!empty($field['data']['min_date'])) {
						$givenDate = $field['data']['min_date'];
						if (!empty($givenDate)) {
							$options['data-min-date'] = $givenDate;
						}
					}
					// set max date
					if (!empty($field['data']['max_date'])) {
						$givenDate = $field['data']['max_date'];
						if (!empty($givenDate)) {
							$options['data-max-date'] = $givenDate;
						}
					}
					// set date format
					if (!empty($field['data']['date_format'])) {
						$options['data-date-format'] = $field['data']['date_format'];
					}
				}

				$fieldObj = $this->input('text', $field['name'], $inputDate, $options);

			} else if ($field['type'] === 'select') {
				// build the options
				$optionsList = [];
				if (!empty($field['options'])) {
					$optionsList = $field['options'];
				} else if (isset($field['options_action'])) {
					// split the action string
					// eg 'ProjectsRepository@allAsList'
					preg_match_all('/^(.*)@(.*)$/i', $field['options_action'], $matches);
					if (!is_array($matches) || !count($matches) === 3)
						throw new Exception("Invalid action {$field['options_action']}.");

					// build the class and fetch the options
					$actionsClass = app($matches[1][0]);

					// if there is an array of arguments, pass that in
					$methodName = $matches[2][0];
					if (isset($field['options_action_params']) && is_array($field['options_action_params'])) {
						$optionsList = $actionsClass->$methodName(...$field['options_action_params']);
					} else {
						$optionsList = $actionsClass->$methodName();
					}
				} else if (isset($field['options_entity'])) {
					$actionsClass = app($field['options_entity']);
					$optionsList = $actionsClass->all()->pluck('name', 'id');
				} else if (isset($field['options_ajax_data_route'])) {
					// automatically add the 'js-select2-ajax' if no class was defined by the user
					if (empty($field['class'])) {
						$options['class'] .= ' select2 js-select2-ajax';
					}
					$options['data-ajax-route'] = route($field['options_ajax_data_route']);
				}

				if (isset($field['multiple'])) {
					$options['multiple'] = 'multiple';
					if (isset($field['group_as_array'])) {
						if (strrpos($field['name'], '[]', -2) !== true) {
							$field['name'] .= '[]';
						}
					}
				}

				$fieldObj = $this->select($field['name'], $optionsList, $field['value'], $options);

			} elseif ($field['type'] === 'file') {

				$fieldObj = $this->file($field['name'], $options);

			} elseif ($field['type'] === 'location') {

				$locationField = new \EMedia\Lotus\Elements\Page\Location\LocationField();

				if (isset($field['config'])) {
					$locationField->withConfig($field['config']);
				}

				if ($this->model) {
					$locationField->withEloquentModel($this->model);
				}

				$fieldObj = $locationField->render();

			}

			// render the fields
			if ($field['type'] === 'hidden') {

				$options = (isset($field['options']))? $field['options']: [];
				$field = $this->hidden($field['name'], $field['value'], $options);
				$renderedContent .= $field->toHtml();

			} else if ($field['type'] === 'file') {
				// create a custom file upload field with options to view and delete the file

				$deleteCheckbox = $this->checkbox("{$field['name']}_delete_file");

				$fileEditableHtml = '<div class="row"><div class="col-md-12">' . $fieldObj->toHtml() . '</div>';
				$fileEditableHtml .= '</div>';

				if (!empty($field['value'])) {
					// try to build the URL for the existing file
					$fileUrl = null;
					if (strpos($field['value'], 'http') === 0) {
						$fileUrl = $field['value'];
					} else {
						if (isset($field['options']) && !empty($field['options']['disk'])) {
							$fileUrl = Storage::disk($field['options']['disk'])->url($field['value']);
						}
					}
					if ($fileUrl === null) $fileUrl = $field['value'];

					// render the delete and view option
					$fileEditableHtml .= '<div class="row">';
					$fileEditableHtml .= '<div class="col-sm-2">' . $deleteCheckbox->toHtml() . ' Delete File</div><div class="col-sm-10">Current File: <a href="' . $fileUrl . '" target="_blank">' . $field['value'] . '</a></div>';
					$fileEditableHtml .= '</div>';
				}

				$fileWrapper = $this->tag('div', $fileEditableHtml);

				// wrap the existing obj with containers
				$fieldWrapper = $this->tag('div', $fileWrapper->toHtml(), [
					'class' => $fieldLayoutClass
				]);

				// TODO: add vertical layouts
				$formGroupWrapper = $this->tag('div',
					$label->toHtml() . $fieldWrapper->toHtml(), [
						'class' => 'form-group row'
					]);

				$renderedContent .= $formGroupWrapper->toHtml();

			} else if (!empty($fieldObj)) {
				$helpWrapper = null;
				if (!empty($field['help'])) {
					$helpWrapper = $this->tag('small', $field['help'], [
						'class' => 'form-text text-muted'
					]);
				}

				// wrap the existing obj with containers
				$fieldWrapper = $this->tag('div', $fieldObj->toHtml() . ($helpWrapper != null ? "\n" . $helpWrapper->toHtml() : ''), [
					'class' => $fieldLayoutClass
				]);

				// TODO: add vertical layouts
				$formGroupWrapper = $this->tag('div',
					$label->toHtml() . $fieldWrapper->toHtml(), [
						'class' => 'form-group row'
					]);

				$renderedContent .= $formGroupWrapper->toHtml();
			}
		}

		return $this->toHtmlString($renderedContent);
	}

	public function renderSubmit()
	{
		$htmlContent = '<div class="form-group row">
            <div class="col-sm-8 offset-sm-4">
                <a href="' . url()->previous() . '" class="btn btn-secondary pull-right">Cancel</a>
                <button type="submit" class="btn btn-success text-right">Save</button>
            </div>
        </div>';

		return $this->toHtmlString($htmlContent);
	}

	/**
	 *
	 * Set the fields from the model's config
	 * eg. Use the following in the model
	 *
	 * 	protected $editable = [
	[
	'name' => 'first_name',
	'display_name' => 'Your first name',
	'value' => '1234',
	],
	[
	'name' => 'last_name'
	],
	[
	'name' => 'title'
	],
	[
	'name' => 'project_status_id',
	'display_name' => 'Project Status',
	'type' => 'select',
	// 'options' => [
	//		1 => 'Upcoming',
	// 		2 => 'Wireframing'
	// ],
	'options_action' => 'App\Modules\Projects\Entities\ProjectStatusesRepository@allAsList'
	]
	];
	 *
	 * @param array $editableFields
	 */
	public function setFields(array $editableFields, $resetExisting = true)
	{

		if ($resetExisting) $this->fields = new Collection();

		foreach ($editableFields as $editableField) {

			if (is_string($editableField)) {

				$editableField = ['name' => $editableField];
				$editableField['type'] = 'text';
				$editableField['display_name'] = $this->getLabelFromFieldName($editableField['name']);
				$editableField['value'] = '';
				$this->fields->push($editableField);

			} else if (!empty($editableField['name'])) {

				// TODO: get the type of the field. Only text type is supported for now
				// $fieldType = $this->getFieldType($editableField);
				if (empty($editableField['type'])) {
					$editableField['type'] = 'text';
				}

				if ($editableField['type'] === 'select' && (
						empty($editableField['options']) && empty($editableField['options_action']) && empty($editableField['options_entity']) && (empty($editableField['options_ajax_data_route'])))) {
					throw new Exception("A select field must have an `options` specifier.");
				}

				// name of the field (i.e. label)
				if (empty($editableField['display_name'])) {
					$editableField['display_name'] = $this->getLabelFromFieldName($editableField['name']);
				}

				// default value of the field
				if (empty($editableField['value'])) {
					$editableField['value'] = '';
				}

				// placeholder
				if (empty($editableField['placeholder'])) {
					$editableField['placeholder'] = '';
				}

				$this->fields->push($editableField);
			}

		}
	}

	public function hasFiles()
	{
		// go through the fields and find if there are any files
		$fileFields = $this->fields->filter(function ($item) {
			return (isset($item['type']) && $item['type'] === 'file');
		});

		return $fileFields->isNotEmpty();
	}

	private function getLabelFromFieldName($fieldName)
	{
		return \Illuminate\Support\Str::title(Text::reverseSnake($fieldName));
	}

	public function setModel(Model $entity)
	{
		$this->model = $entity;

		// set the fields
		if (!method_exists($entity, 'getEditableFields')) return false;

		// set the field parameters
		$this->setFields($entity->getEditableFields());

		// set default values
		$this->setFieldValuesFromModel($entity);
	}

	/**
	 *
	 * Set the default values of the fields
	 *
	 * @param $entity
	 */
	public function setFieldValuesFromModel(Model $entity)
	{
		// copy the field values, because we'll modify this later
		$fields = $this->fields;

		// look if the model has a the values set, and if so, add them to the fields
		foreach($fields as $fieldData) {
			// get the field name
			$value = $entity->getAttributeValue($fieldData['name']);

			if ($value !== null) {
				$this->setFieldValue($fieldData['name'], $value);
			} else {
				// see if this is an array,
				// if so, we should retrieve the relationship values
				if (strpos($fieldData['name'], '[]') !== false) {
					if (!empty($fieldData['relationship'])) {
						$relationshipName = $fieldData['relationship'];
						$relationshipIds = $entity->$relationshipName()->pluck('id')->toArray();
						$this->setFieldValue($fieldData['name'], $relationshipIds);
					}
				}
			}
		}
	}

	/**
	 *
	 * Go set the value of field(s) to the given value
	 *
	 * @param $fieldName
	 * @param $value
	 *
	 * @return bool
	 */
	private function setFieldValue($fieldName, $value)
	{
		// we'll let the incoming value to be set on field as it is.
		// if (empty($value)) return false;

		// go through all fields, and update the given value
		$this->fields->transform(function ($fieldData) use ($fieldName, $value) {
			if ($fieldData['name'] === $fieldName) {
				$fieldData['value'] = $value;
			}
			return $fieldData;
		});

		return true;
	}


}
