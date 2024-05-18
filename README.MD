Finglish to Persian Converter
=============================

This library provides a Finglish-to-Persian convertor.

The return value is a list of possibilities, each with a confidence value in the [0.0, 1.0] range.

```php
<?php

// checking the exists Library.
if (!file_exists("finglish.php")) {
  copy('https://raw.githubusercontent.com/hctilg/finglish/main/index.php', 'finglish.php');
}

// require Library.
require_once('finglish.php');

$model = new Finglish();

var_dump( $model("aseman abi") );  // "آسمان آبی"
```