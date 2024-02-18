# Auto-form Builder from Eloquent Models for Laravel

By default, it renders a Bootstrap, horizontal form layout.

## Version Compatibility

| Laravel Version | Oxygen Version | Branch       |
|----------------:|----------------|--------------|
|             v10 | 2.x            | 2.x          |
|              v9 | 1.x            | 1.x          |
|              v8 | 0.5            | version/v0.4 |
|              v7 | 0.3            | version/v0.3 |

See [CHANGE LOG](CHANGELOG.md) for version history.

## Installation

1. Add the following repositories to your `composer.json`

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@bitbucket.org:elegantmedia/formation.git"
    },
    {
        "type": "vcs",
        "url": "git@bitbucket.org:elegantmedia/lotus.git"
    }
]
```

2. Require the package through the command line

```
composer require emedia/formation
```

## How to use

**Prepare the model**
```
	use GeneratesFields;

	protected $editable = [
		[
			'name' => 'first_name',
			'display_name' => 'Your first name',    // (optional label)
			'value' => '1234',  // (optional value)
			'help' => 'Helper text to explain what the field does.', // (optional)
		],
		[
			'name' => 'last_name'
		],
		'title',
		[
			'name' => 'office_location_id',
			'display_name' => 'Office Location',
			'type' => 'select',

			// Method 1 - retrieve all items. eg. OfficeLocation::all();
			'options_entity' => 'App\Modules\HumanResources\Entities\OfficeLocation',

			// Method 2 - use `options_action` to call a method from repository
			// 'options_action' => 'App\Modules\HumanResources\Entities\ProjectsRepository@allAsList',
			// optional - you can pass an array of parameters to the 'options_action' method
			// 'options_action_params' => [$entity->id],

			// Method 3 - give the options directly
			// 'options' => [
            // 		'oneworld' => 'OneWorld',
            //		'skyteam' => 'SkyTeam',
            //	]

            // Method 4 - load entities from AJAX requests
            // [
	        //     'name' => 'external_inspector_id[]',
	        //    'display_name' => 'External Inspectors',
	        //    'type' => 'select',
	        //    'multiple' => true,   // optional
	        //    'options_ajax_data_route' => 'manage.properties.inspectors.json', // this is the route to return the data
	        // ],
		],
		[
			// multi-option selects
            'name' => 'destination_id[]',
            'display_name' => 'Destinations',
            'type' => 'select',
            'multiple' => true,
            'relationship' => 'destinations',       // optional. Use to resolve the relationship automatically.
            'options_entity' => Destination::class, // or use methods from single-selects (as listed above)
            'class' => 'select2',
            'value' => [1],     // default value
        ],
		[
			// this configuration is for multi select checkbox drop down.
            'name' => 'product_ids',
            'display_name' => 'Products',
            'type' => 'select',
            'multiple' => 'multiple', // *for multiple selection feature
            'group_as_array' => true,	// group input field as array (i.e. add `[]` to the end of field name)
            'relationship' => 'products', 
            'options_entity' => Product::class,
            'class' => 'multicheck', // *make the class as multi check
            'value' => [1], 
        ],
        [
			'name' => 'joined_at',
			'display_name' => 'Joined Date',
			'type' => 'date',
			'data' => [
				// TODO: subtract-x-days, add-x-days
				'min_date' => '01/May/2010',
				'max_date' => null,
			]
		],
        [
			'name' => 'currency',
			'type' => 'select',
			'options' => [
				'LKR' => 'LKR',
				'AUD' => 'AUD'
			]
		],
		[
			'name' => 'logo_file_url',              // this should match with `url_column` if you want the ablity to delete
			'display_name' => 'Logo',               // the file name to display in the form
			'type' => 'file',
			'options' => [
				'disk' => 'club_logos',     // required
				'use_db_prefix' => 'logo',	// required
				// 'folder' => '',                   // optional
				// 'generate_thumb' => false,        // optional - default is false
				// 'is_image' => false,              // optional - default is false
				// 'delete_from_disk' => false,      // optional - default is false
			],
		],
        [
            'name' => 'Address',
            'type' => 'location',
        ],
	];

```

In the controller
```
	$entity = new User();
	$entity->first_name = 'Kim';
	$entity->last_name = 'Kardashian';

	$form = new Formation($entity);

	return view('user.profile', compact('form'));
```

Then in the view
```
{{ $form->render() }}
{{ $form->renderSubmit() }}
```

### Configuration Options

To give more configuration options, instead of using the `$editable` property, override the `getEditableFields()` method. If you're using this method, the `$editable` property should be deleted from the model for clarity.

Following example shows how to customise the `location` field and add multiple Google Autocomplete Address fields.

```
public function getEditableFields()
{
	return [
		'name',
		[
			'name' => 'return_address',
			'display_name' => 'Return Address',
			'type' => 'location',
			'config' => lotus()->locationConfig()
							   ->setInputFieldPrefix('map3_')
							   ->setSearchBoxElementId('js-search-box3')
							   ->setMapElementId('map3')
							   ->setAutoCompleteOptions([
							        'types' => ['establishment'],
							        'componentRestrictions' => [
							            'country' => 'nz',
									]
							   ])
		],
		[
			'display_name' => 'Phone',
			'name' => '_map3_phone',        // let the auto-complete fill the phone number as well
			'class' => 'js-autocomplete',
		],
		'email',
	];
}
```

### API
```
$form = new Formation($entity);

// optional

// set fields manually
// $form->setFields($entity->getEditableFields());

// set the values from model
// $form->setFieldValuesFromModel($entity);

// set individual field values
// $form->setFieldValue('first_name', 'Khloe');
```

## Handling Dropdowns

### Option 1 - Multiselect (with only HTML)

The dropdown items can be rendered directly from a list. Use this method only if there is a limited amout of items to select from. For 200+ items, use the AJAX  method listed below.

```
use EMedia\Formation\Entities\GeneratesFields;
use \Illuminate\Database\Eloquent\Model;

class Project extends Model
{
	use GeneratesFields;
	
	protected $editable = [
		[
			'name' => 'role_id[]',
			'display_name' => 'Role',
			'options_action' => '\App\Entities\Auth\RolesRepository@dropdownList',
			// 'options_action_params' => [true],		// optional (these wll be passed to the method above)
			'type' => 'select',
			'relationship' => 'roles', 					// this is the related method. This will only be used when the name is an array.
			'multiple' => true,
			'help' => 'Select 1 or more roles.',		// optional
		]
	];
	
	public function roles()
	{
		return $this->belongsToMany(\App\Entities\Auth\Role::class);
	}
}
```

The repository should return the items for the method listed in `options_action`.

```
namespace App\Entities\Auth;

class RolesRepository 
{

	public function dropdownList()
	{
		$all = \App\Entities\Auth\Role::orderBy('title')->get();
	
		$results = [];
		foreach ($all as $item) {
			$results[$item->id] = $item->title;
		}
	
		return $results;
	}

}
```

### Option 2 - Multiselect (with JS/Bootstrap)

Add multiselect.css and multiselect.js to your page.
[Boostrap Multiselect js Page](https://github.com/davidstutz/bootstrap-multiselect)
```
	$('.multicheck').multiselect({
            includeSelectAllOption: true
    });
```

If you want to change this class(multicheck) to different, change it in the model configuration
array also.


### Option 3 - Multiselect (with AJAX, Typeahead)

Add this to the scripts

```

	$('.js-select2-ajax').each(function (index, element) {
		var $el = $(element);
		var ajaxUrl = $el.data('ajax-route');
		if (ajaxUrl) {
			$el.select2({
				ajax: {
					url: ajaxUrl,
					dataType: 'json',
					processResults: function (response) {
						if (response.payload) {
							return {
								results: response.payload,
							}
						}
						return [];
					}
				},
				minimumInputLength: 2,
			})
		}
	});

```

You should convert the array into the following format.

```
$responseData = $users->map(function ($item) {
	return [
		'id' => $item->id,
		'text' => $item->full_name,
	];
});


```

And return the response through apiSuccess function.


## Copyright

Copyright (c) Elegant Media
