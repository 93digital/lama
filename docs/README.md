# Lama

LAMA _(Italian word for BLADE)_ is a utility class that helps you to quickly implement the _Load More_ & _dynamic filtering_ functionality in WordPress.

**NOTE**: Php >= 7.2 is required earlier version are not supported.

## Installation

### Composer

Run the following command in your terminal to install Lama with [Composer](https://getcomposer.org/)

```bash
$ composer require "93digital/lama @dev"
```

Below is a basic example of getting started with the class, though your setup may be different depending on how you are using composer.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

\Nine3\Lama::init();
```

### Install manually

Download/Clone this repository and load the main class file into your theme/plugins like so:

```php
require_once 'lama/class-lama.php';

\Nine3\Lama::init();
```

## Development

[npm](https://www.npmjs.com/)/[yarn](https://yarnpkg.com/lang/en/) is needed to compile the JS file.

<FlexWrapper class="full-bleed">

<ColumnWrapper width="48%" class="column--half" title="npm">

```bash
npm install
```

</ColumnWrapper>

<ColumnWrapper width="48%" class="column--half" title="yarn">

```bash
yarn install
```

</ColumnWrapper>
</FlexWrapper>

### Build the JS

<FlexWrapper class="full-bleed">

<ColumnWrapper width="48%" class="column--half" title="npm">

```bash
npm run build
```

</ColumnWrapper>

<ColumnWrapper width="48%" class="column--half" title="yarn">

```bash
yarn build
```

</ColumnWrapper>
</FlexWrapper>

## Debug

When the [debug is enabled](https://codex.wordpress.org/Debugging_in_WordPress) in WordPress LAMA automatically outputs, in the `debug.log` file, the following information:

- the [\$args](/docs/HOOKS-FILTERS.html#wp-query-parameters) array parameters passed to the WP_Query (when performing the ajax request)
- the [template](/docs/USAGE.html#use-lama-with-custom-wp-query) that is trying to load for each element found

In alternative is possible to set the constant `LAMA_DEBUG` to `true` to output that information inside the `wp-content/lama.log` file, add the following line to your starter theme/plugin:

```php
define( 'LAMA_DEBUG', true );
```

## Documentation

The class comes with an extensive documentation available [here](https://93digital.gitlab.io/lama/)
