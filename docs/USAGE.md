# Usage

## Initialise the class

Add the following line your _functions.php_ file, or inside the main script of your plugin:

```php
\Nine3\Lama::init( $basic_css = true );
```

#### Parameters

| parameter   | Type    | Required | Default | Description                                                    |
| ----------- | ------- | -------- | ------- | -------------------------------------------------------------- |
| \$basic_css | boolean |          | true    | Loads the basic CSS used to style/animate the custom dropdown. |

## Use the class in your template

Example:

```php
Nine3\Lama::start( [name], [class name], [auto load], [query] );

Nine3\Lama::container_start( [class name] );

...[ your custom loop goes here ]...

Nine3\Lama::container_end( [pagination] );

Nine3\Lama::end( [show load more button], [button label] );
```

_Simple, isn't it?_

### Start

This function is needed by Lama to generate the HTML `<form>` tag with all its attributes required to work properly.

#### Usage

```php
Nine3\Lama::start( [name], [class name], [auto load], [query] );
```

#### Parameters

| parameter  | Type     | Required | Default | Description                                                                                      |
| ---------- | -------- | -------- | ------- | ------------------------------------------------------------------------------------------------ |
| name       | string   |          |         | the "name" to assign to the form. _This parameter is also used to run all the internal filters_. |
| class name | string   |          |         | the custom class name to assign to the form                                                      |
| auto load  | boolean  |          | false   | auto load new elements on page scroll (infinite scroll).                                         |
| \$query    | WP_Query |          |         | needed when using a custom loop to set up the data needed for LAMA to work                       |

#### Example

Using LAMA with the main WP_Query:

```php
Nine3\Lama::start( 'my-name' );
```

Using LAMA with a custom query:

```php
$posts = new WP_Query(
  'post_type'   => 'post',
  'post_status' => 'publish',
  'lama'        => '[FORM_NAME]', // This parameter is needed!
);

Nine3\Lama::start( 'my-name', '', false, $posts );
```

Passing the custom query to LAMA is needed to let the class know which post type to load, tax/meta query, and other parameters, to apply when performing the LOAD MORE / Filtering.

**NOTE**: The parameter `'lama' => '[FORM_NAME]` is needed to [restore the filters](/docs/UTILITY.html#restore-the-filters-on-page-refresh) used on the page load.
**NOTE**: The parameter `[FORM_NAME]` is the name of the form passed to the (Start)[/docs/USAGE.html#start].

### The container

The container is needed and used by the class to append/refresh the content loaded via ajax.

#### Usage

```php
/* start container */
Nine3\Lama::container_start( [class name] );

/* close the container */
Nine3\Lama::container_end( [ pagination ]);
```

| parameter  | Type    | Required | Default | Description                                                            |
| ---------- | ------- | -------- | ------- | ---------------------------------------------------------------------- |
| class name | string  |          |         | the custom class name to assign to the container                       |
| pagination | boolean |          | false   | if true use a custom version of the wp pagination that works with ajax |

**NOTE**: [customise the pagination](/docs/HOOKS-FILTERS.html#hidden-fields) template.

**NOTE**: The container is going to be emptied when the element that submits the form does not have the `lama-submit` class.

### End

```php
Nine3\Lama::end( [load more], [ button label ] );
```

| parameter    | Type    | Required | Default   | Description                                                                                  |
| ------------ | ------- | -------- | --------- | -------------------------------------------------------------------------------------------- |
| load more    | boolean |          |           | if true adds a submit button with the LOAD MORE label (check the note for more information). |
| button label | string  |          | LOAD MORE | allow customising the text of the &lt;button&gt; element                                     |

**NOTE**: check the [custom "Load More" button](/docs/USAGE.html#use-lama-with-custom-wp-query) section to see how to use a custom submit form element.
