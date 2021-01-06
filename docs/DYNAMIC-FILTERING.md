# Dynamic filtering

The class offers built-in solutions to generate dropdowns, checkbox and radio buttons list for the specified taxonomy or a custom list.

## Taxonomy

To add filter by taxonomy just use:

```php
add_taxonomy_filter( $taxonomy, $args, $style );
```

- \$taxonomy (String) the taxonomy slug
- \$args (Array) array of arguments used to style the dropdown:
  | argument | description |
  |---|---|
  |after| The custom HTML to print after the dropdown|
  |before| The custom HTML to print before the dropdown|
  |class| the class name prefix to use for the HTML elements to be created. _NOTE_ All the class names for the child elements follow the [BEM Metodology](http://getbem.com/introduction/) |
  | container_class | class to assign to the &lt;select&gt; and the main container generated when using the custom-style |
  |placeholder| default label to be shown, it is also used to allow the client to "remove" that filter |
  |clearable| if true the placeholder can be selected to clear the filter. \_Default true\* |
  |custom-style| (For dropdowns only) If set to true, generates also a custom dropdown that can be easily styled. \_Default: true\*|
  || also a normal &lt;select&gt; element is rendered to be used as native elements for mobile or when JS is not available.
  |icon| - dropdown icon (for dropdowns)|
  || - radio/checkboxes the icon is added on the right of the label|
  |item_after| (for Checkbox/Radio only) the custom HTML to print after the input & radio one|
  |item_before| (for Checkbox/Radio only) the custom HTML to print before the input & radio one|
  |term-args| Array of arguments to be passed as 2nd argument of the [get_terms](https://developer.wordpress.org/reference/functions/get_terms/) function|
  |button-type| The button type inside the custom select (default is Submit)|
  |is-meta-filter| Need to let LAMA know how to treat this filter as meta|

- \$style (String) which style to use to render the list of taxonomy.
  |value|description|
  |---|---|
  |\Nine3\Lama::SELECT| &lt;select&gt; (single option only)._Default_|
  |\Nine3\Lama::CHECKBOX| Checkboxes (multiple options can be selected) |
  |\Nine3\Lama::RADIO| Radio (single option only) |

Structure of the HTML generated when using the option "custom-style":

::: vue
&lt;div class="lama__dropdown [CONTAINER CLASS][class]"&gt;
├── &lt;button type="[BUTTON TYPE]" class="lama__dropdown\_\_selected [CLASS]\_\_selected"&gt;
│   ├── &lt;span class="lama__dropdown\_\_selected\_\_label [CLASS]\_\_span">[PLACEHOLDER]&lt;
│   └── &lt;span class="lama__dropdown\_\_selected\_\_icon [CLASS]\_\_icon"&gt;
│   └─── [ICON]
├── &lt;div class="lama__dropdown\_\_list [CLASS]\_\_list"&gt;
│   ├── &lt;ul class="lama__dropdown\_\_list\_\_items [CLASS]\_\_list\_\_items"&gt;
│   └─── &lt;li class="lama__dropdown\_\_list\_\_item [CLASS]\_\_list\_\_item"&gt;
│   └─── &lt;button type="[BUTTON TYPE]" data-filter="filter-[NAME]" class="lama__dropdown\_\_list\_\_button [CLASS]\_\_list\_\_button" name="filter&gt;[NAME]" value="">
:::

### Usage

```php
  Nine3\Lama::add_taxonomy_filter(
    'industry', [
      'class'        => 'm14__filter',
      'placeholder'  => __( 'All industries', 'my-theme' ),
      'icon'         => nine3_get_svg( 'arrow-down', false ),
    ]
  );

  // Style parameter using CHECKBOX
  Nine3\Lama::add_taxonomy_filter(
    'industry', [
      'class'        => 'm14__filter',
      'placeholder'  => __( 'All industries', 'my-theme' ),
      'icon'         => nine3_get_svg( 'arrow-down', false ),
    ],
    \Nine3\Lama::CHECKBOX
  );

  // Wrap items in custom HTML elements
  echo '<ul>';
  Nine3\Lama::add_taxonomy_filter(
    'industry', [
      'class'  => 'm14__filter',
      'before' => '<li class="item">',
      'after'  => '</li>',
    ],
    \Nine3\Lama::RADIO
  );
  echo '</ul>';
```

### Example output for Radio / Checkbox style

```html
<input
  class="sidebar__date-item lama__radio lama-filter"
  type="radio"
  id="filter-publication-year-2017"
  name="filter-publication-year"
  value="2017"
  data-filter="filter-publication-year"
/>
<label
  class="sidebar__date-item__label lama__radio__label"
  for="filter-publication-year-2017"
  >2017</label
>
```

#### Example output using the 'after' and 'before' parameter:

```php
echo '<ul class="no-list sidebar__dates flex flex-wrap">';

\Nine3\Lama::add_taxonomy_filter( 'taxonomy-name, [
  'before' => '<li class="sidebar__date-item">',
  'after'  => '</li>
] );

echo '</ul>';
```

output:

```html
<ul class="no-list sidebar__dates flex flex-wrap">
  <li class="sidebar__date-item">
    <input
      class="sidebar__item lama__radio lama-filter"
      type="radio"
      id="filter-publication-year-2017"
      name="filter-publication-year"
      value="2017"
      data-filter="filter-publication-year"
    />
    <label
      class="sidebar__item__label lama__radio__label"
      for="filter-publication-year-2017"
      >2017</label
    >
  </li>
  <li class="sidebar__date-item">
    <input
      class="sidebar__item lama__radio lama-filter"
      type="radio"
      id="filter-publication-year-2018"
      name="filter-publication-year"
      value="2018"
      data-filter="filter-publication-year"
    />
    <label
      class="sidebar__item__label lama__radio__label"
      for="filter-publication-year-2018"
      >2018</label
    >
  </li>
</ul>
```

## Search field

```php
Nine3\Lama::add_search_filter( $args );
```

- `$args` (Array) of arguments used to customise the search field

| argument    | description                                                                            |
| ----------- | -------------------------------------------------------------------------------------- |
| class       | the custom class name to use for the HTML elements to be created                       |
| placeholder | default label to be shown, it is also used to allow the client to "remove" that filter |
| debounce    | ms to wait before performing the load more. `Default 10ms`                             |
| icon        | if set prints the icon inside a &lt;button&gt; element                                 |

### Usage

```php
Nine3\Lama::add_search_filter(
    [
        'placeholder' => __( 'Enter your search term...' ),
        'icon'        => nine3_get_svg( 'search-icon-menu', false ),
        'class'       => 'm14__search',
    ]
);
```

## Custom filter

Is possible to use the built-in filter functions to generate [dropdown](#dropdown-example)/[checkbox](#checkbox-example) and [radio](#radio-example) inputs by passing custom values.

### Example using dropdown (select)

Show the custom filter as a select element:

`add_dropdown_filter( array $args = [] )`

- \$args (Array) array of arguments used to style the dropdown:
  | argument | description |
  |---|---|
  |name| the filter name (Required!) |
  |after| The custom HTML to print after the dropdown|
  |before| The custom HTML to print before the dropdown|
  |class| the class name prefix to use for the HTML elements to be created. _NOTE_ All the class names for the child elements follow the [BEM Metodology](http://getbem.com/introduction/) |
  | container_class | class to assign to the &lt;select&gt; and the main container generated when using the custom-style |
  |placeholder| default label for the filter (rather than showing the first option by default) |
  |clearable| Adds an option to clear the filter, if set to true will display 'Show All', alternatively add a string here to show a custom label. \_Default: true*|
  |custom-style| (For dropdowns only) If set to true, generates also a custom dropdown that can be easily styled. \_Default: true*|
  || also a normal &lt;select&gt; element is rendered to be used as native elements for mobile or when JS is not available.
  |icon| - dropdown icon (for dropdowns)|
  || - radio/checkboxes the icon is added on the right of the label|
  |item_after| (for Checkbox/Radio only) the custom HTML to print after the input & radio one|
  |item_before| (for Checkbox/Radio only) the custom HTML to print before the input & radio one|
  |button-type| The button type inside the custom select (default is Submit)|
  |term-args| Array of arguments to be passed as 2nd argument of the [get_terms](https://developer.wordpress.org/reference/functions/get_terms/) function|
  |is-meta-filter| Need to let LAMA know how to treat this filter as meta|

Structure of the HTML generated when using the option "custom-style":

::: vue
&lt;div class="lama__dropdown [CONTAINER CLASS][class]"&gt;
├── &lt;button type="[BUTTON TYPE]" class="lama__dropdown\_\_selected [CLASS]\_\_selected"&gt;
│   ├── &lt;span class="lama__dropdown\_\_selected\_\_label [CLASS]\_\_span">[PLACEHOLDER]&lt;
│   └── &lt;span class="lama__dropdown\_\_selected\_\_icon [CLASS]\_\_icon"&gt;
│   └─── [ICON]
├── &lt;div class="lama__dropdown\_\_list [CLASS]\_\_list"&gt;
│   ├── &lt;ul class="lama__dropdown\_\_list\_\_items [CLASS]\_\_list\_\_items"&gt;
│   └─── &lt;li class="lama__dropdown\_\_list\_\_item [CLASS]\_\_list\_\_item"&gt;
│   └─── &lt;button type="[BUTTON TYPE]" data-filter="filter-[NAME]" class="lama__dropdown\_\_list\_\_button [CLASS]\_\_list\_\_button" name="filter&gt;[NAME]" value="">
:::

### Dropdown example

The code below shows the Sort filter:

```php
Nine3\Lama::add_dropdown_filter( [
  'name'        => 'sort',
  'class'       => 'm32__sort',
  'placeholder' => 'Sort by',
  'clearable'   => false,
  'values'      => [
    'ASC'        => __( 'Ascending', 'my-theme' ),
    'DESC'       => __( 'Descending', 'my-theme' ),
  ],
  'icon' => nine3_get_svg( 'arrow-down', false ),
] );
```

### Radio example

```php

Nine3\Lama::add_radio_filter(
  [
    'name'        => 'sort',
    'class'       => 'm32__sort',
    'placeholder' => 'Sort by',
    'clearable'   => false,
    'values'      => [
      'ASC'        => __( 'Ascending', 'my-theme' ),
      'DESC'       => __( 'Descending', 'my-theme' ),
    ],
    'icon' => nine3_get_svg( 'arrow-down', false ),
  ]
);
```

### Checkbox example

```php

Nine3\Lama::add_checkbox_filter(
  [
    'name'        => 'sort',
    'class'       => 'm32__sort',
    'placeholder' => 'Sort by',
    'clearable'   => false,
    'values'      => [
      'ASC'        => __( 'Ascending', 'my-theme' ),
      'DESC'       => __( 'Descending', 'my-theme' ),
    ],
    'icon' => nine3_get_svg( 'arrow-down', false ),
  ]
);
```

## Filter Meta fields

You just need to pass the property `is-meta-filter = true`:

### Example with dropdown list

```php
$meta = [
  'value1' => 'Label 1',
  'value2' => 'Label 2',
];

Nine3\Lama::add_dropdown_filter(
  [
    'name'           => 'my_meta_key',
    'placeholder'    => __( 'Select meta field', 'my-theme' ),
    'values'         => $meta,
    'is-meta-filter' => true,
  ]
);
```

### Example with checkboxes

```php
$meta = [
  'value1' => 'Label 1',
  'value2' => 'Label 2',
];

Nine3\Lama::add_checkbox_filter(
  [
    'name'           => 'my_meta_key',
    'placeholder'    => __( 'Select meta field', 'my-theme' ),
    'values'         => $meta,
    'is-meta-filter' => true,
  ],
);
```

### Example with radio inputs

```php
$meta = [
  'value1' => 'Label 1',
  'value2' => 'Label 2',
];

Nine3\Lama::add_radio_filter(
  [
    'name'           => 'my_meta_key',
    'placeholder'    => __( 'Select meta field', 'my-theme' ),
    'values'         => $meta,
    'is-meta-filter' => true,
  ]
);
```
