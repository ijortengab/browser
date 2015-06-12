### Standard Example for Scratch

Use this example if you working without [composer](http://getcomposer.org).

```php
<?php
require('browser.php');
use IjorTengab\browser;
$url = 'http://localhost/GitHub/browser/test2.php';
$b = new browser();
$b->setUrl($url);
$b->browse();
$result = $b->result;
print_r($result);

```

Another example for Scratch (Short code)

```php
<?php
require('browser.php');

use IjorTengab\browser\browser;

$url = 'http://localhost/GitHub/browser/test2.php';
$result = new 
?>
