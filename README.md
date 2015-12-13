Browser, PHP HTTP Client
========================

HTTP Requester like a browser, automatically save and load cookie, 
follow location, and save the history and cache.

```php
// Simple request.
$browser = Browser::profile('Mozilla Firefox on Windows 7');
$html = (string) $browser->setUrl('http://httpbin.org/html')->execute()->result;
```

```php
// Post request, login, and enter to authenticated page.
$browser = Browser::profile('Mobile');
$browser->setUrl('http://httpbin.org/post');
$browser->post([
    'username' => 'IwanFals',
    'password' => 'SoreTuguPancoran',
]);
$browser->headers('Referer', 'http://httpbin.org/');
$browser->options('timeout', 5);
$browser->execute();
// Cookie automatically saved.
$browser->reset()->setUrl('http://httpbin.org/member-area-only')->execute();
// Cookie automatically loaded, and with session information in cookie
// you can enter page which is for authenticated only.
$code = $browser->result->code; // 200 OK.
$html = $browser->result->data;
```

