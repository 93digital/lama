# Advanced

## Use LAMA with custom WP_Query

You can also use LAMA with custom loops by passing it to the [start](#start) function.

```php
// Our custom loop
$loop = new WP_Query( [
  'post_type' => 'custom',
  'lama' => '1',
] );

// ...

// Here we have to pass our custom loop as 4th parameter to the ::start function.
Nine3\Lama::start( 'custom', 'another-class', false, $loop );
```

**NOTE**: The `'lama' => '1'` parameter is used to let LAMA [restore all the filters on page load](/docs/UTILITY.html#restore-the-filters-on-page-refresh) and also [run the custom filters](/docs/HOOKS-FILTERS.html#wp-query-parameters).

## The single item template

When looping through the items found the app will:

The template name for the single item has to be stored into the _template-parts_ folder and named:

```html
template-parts/[POST_TYPE]-single-item.php
```

**NOTE**: check [using a custom single item template](/docs/HOOKS-FILTERS.html#using-a-custom-single-item-template) to specify a different template to use.

## Customise the "Load More" button

If you want/need to use a custom **Load more** button, instead of the built-in provided by the [end](#end), add a simple HTML submit button between the `start` and `end` functions.

```html
<!-- Normal input type submit -->
<input type="submit" value="Custom load more button" />

<!-- Or you can use a <button> -->
<button type="submit">This is my custom load more button</button>
```

**NOTE**: If you're adding your custom button inside LAMA container, you have to also output it for every ajax request, because the container will be automatically emptied. (See note below)

**NOTE**: If you're custom button has to append items to the container, instead of emptying it, your custom element **MUST** use the class `lama-submit`:

```html
<!-- This custom button triggers the `load-more` functionality -->
<input type="submit" value="Load more" class="lama-submit" />
```

## Posts found

`posts_found( [singular label], [plural label] );`

You can use the built-in `posts_found` function to display the # of posts found, and LAMA will take care to
update automatically update it when performing dynamic filtering :).

### Example

```php
Nine3\Lama::posts_found(
  __( '%d result found', 'my-theme' ),
  __( '%d results found', 'my-theme' )
);
```
