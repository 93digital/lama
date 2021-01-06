# Utility

## Transitions

When new elements get added by LAMA, the 'loaded' class is added to them. You can use that to transition them using the JS hook.

### Example

**NOTE:** `lama-item` is a generic class used for reference only, replace it with the class used by your single item.

```css
.lama-item {
  opacity: 1;
  transition: all 0.3s ease;
}

.lama-item.loaded {
  opacity: 0;
}
```

```js
// We need to remove the "loaded" class in order to transition the elements
$('body').on('lamaDone', (event, $container, $items) => {
  $items.removeClass('loaded');

  /**
     * Delay transition of the loaded elements
     *
    $items.each((index, item) => {
        setTimeout($item => {
            $item.removeClass('loaded');
        }, 50 * index, $(item));
    });
    */
});
```

## Reset the form

If you want to reset the form data add:

```html
<input type="reset" value="Reset" />
```

## Restore the filters on page refresh

When applying filters or load more to the **main query**, the class will automatically append `?query=main` to the URL, this is needed to let LAMA know to apply all the filters present in the URL when the page is loaded.
So, there is no need to apply them using the `pre_get_posts` filter manually.

But, when working with custom queries, you have to manually allow LAMA to filter it for you by just passing the
parameter `lama=1` to your WP_Query.

### Example

```php
$custom_query = new WP_Query( [
    ...
    'lama' => 1,
    ...
] );
```

`Simple, isn't it?`

## Trigger Lama programmatically

To trigger lama, trigger its submit form.

```js
jQuery('form.lama').trigger('submit');
```

## Use LAMA with a custom Query

Is possible to use LAMA to generate all the HTML needed and to handle the ajax request and its response, when dialling with custom output, for example when retrieving data from a custom query.

### Example

Suppose we want to generate the output from a custom table called `custom_table`, we need to generate the LAMA containers as shown in the [Usage](/docs/USAGE.html) help page. And use a custom loop to print our items.

```php
global $wpdb;

$results = $wpdb->get_results( 'SELECT * FROM custom_table ORDER BY custom_column' );

Nine3\Lama::start( 'custom_table' );

Nine3\Lama::container_start( 'custom-table' );

foreach ( $results as $result ) {
    echo '<div>' . $result->field_name . '</div>';
}

Nine3\Lama::container_end( false );

Nine3\Lama::end( true, 'MORE CUSTOM DATA!' );
```

In the example above we're printing the data from a custom query, so now we can't use the WP_Query to keep loading or filtering the items, but we can still let LAMA empty the current container and populate it with our custom HTML response.

#### Stop LAMA from running WP_Query

That's easy, we just need to pass an empty array to LAMA's filter [`lama_args`](/docs/HOOKS-FILTERS.html#wp-query-parameters):

```php
// If our filter does not return an array LAMA will not execute the WP_Query and the resulting loop.
add_filter( 'lama_args_custom_table', '__return_false' );
```

#### The custom items

To print our custom items, we can use one of the 2 filters LAMA offers:

1. [Before the loop](/docs/HOOKS-FILTERS.html#before-the-loop)
2. [After the loop](/docs/HOOKS-FILTERS.html#after-the-loop)

Let's use the 1st one:

```php
/**
 * @param array $posts in this case this parameter contains just an empty array
 * @param mixed $args this contains whatever we passed in the previous filter
 * @param array $params this array contains the data we got from the FORM.
 */
do_action( 'lama_before_loop_custom_table', function( $posts, $args, $params ) {
    global $wpdb;

    Nine3\Lama::container_start( 'custom-table' );

    foreach ( $results as $result ) {
        echo '<div>' . $result->field_name . '</div>';
    }
}, 10, 3 );
```

That's all. Now LAMA will replace the current items with the custom HTML generated in our loop.

##### The FORM data

Considering the example above, we can also access all the data present in the form using the variable `$params` (the 3rd parameter).

So, suppose we need some custom data to be passed to the back-end, we can add a hidden input to our LAMA form:

```php
global $wpdb;

$results = $wpdb->get_results( 'SELECT * FROM custom_table ORDER BY custom_column' );

Nine3\Lama::start( 'custom_table' );

Nine3\Lama::container_start( 'custom-table' );

<input type="hidden" name="i-am-hidden" value="1">

foreach ( $results as $result ) {
    echo '<div>' . $result->field_name . '</div>';
}

Nine3\Lama::container_end( false );

Nine3\Lama::end( true, 'MORE CUSTOM DATA!' );
```

now we can retrieve the data of our custom `i-am-hidden` parameter inside the filter:

```php
add_action( 'lama_before_loop_custom_table', function( $posts, $args, $params ) {
    echo (int) $params['i-am-hiddem']; // 1
}, 10, 3);
```

**NOTE** all the custom elements inside LAMA's container will be removed on each AJAX call, while everything inside `start` and `end` will be kept.
So, if you need to update some input on each ajax call put it **between** `container_start` and `container_end`, otherwise outside that functions.
Also, data from the form is not escaped/sanitised, so make sure you do it!

## Use lama to filter the WP_Query parameters

You can use LAMA to automatically generate the `tax_query` arguments needed for the WP_Query loop, by using the internal `filter_wp_query` function:

```php
$main_filters = [
    'slug-1' => 'Category 1',
    'slug-2' => 'Category 2',
];

$args = Lama::filter_wp_query(
    [
        'event-categories' => array_keys( $main_filters ), // event-categories is the name of the taxonomy to filter.
        'meta-key'         => 'meta-value', // key is the name of the "meta" field we want to use.
    ],
    $args
);

$custom = new WP_Query( $args );
```

**NOTE:** when using the function to filter by `meta_key`, the key we want to prefix has to be starting with `meta-`

Of course, you can also just manually create the [tax_query](https://codex.wordpress.org/Class_Reference/WP_Query#Taxonomy_Parameters) or [meta_query](https://codex.wordpress.org/Class_Reference/WP_Meta_Query) in the (old fashion way).

## Debug

Turning on the debug can be useful to check what parameters LAMA is using to generate the WP_Query. The class will also output debug message about the internal parameters and the template is trying to load.

By default LAMA outputs the message into the `debug.log` file, if the [debug is enabled](https://codex.wordpress.org/Debugging_in_WordPress).
In alternative is possible to set the constant `LAMA_DEBUG` to `true` to output that information inside the `wp-content/lama.log` file, add the following line to your starter theme/plugin:

```php
define( 'LAMA_DEBUG', true );
```

It's also possible to use the internal debug function to output your own message:

```php
/**
 * Show debug message, if WP_DEBUG is defined and enabled.
 *
 * @param mixed $message the log message. It will be converted in string using the print_r function.
 * @return void
 */

function debug( $string, $prefix = '' ) {}
```

### Usage

```php
\Nine3\Lama::debug( 'Hello there', 'prefix ' );

// Output will be:
// [date & time] LAMA: prefix Hello there
```
