# TsukinoMito

Slack Outgoing WebHooks dispatcher

---

1. Set slack credentials to `.env`.

2. Create component class.  
src/components/Foo.php
```
namespace App\Components;

use App\Response;

class Foo
{
    use Response;

    public static function run($request)
    {
        self::response('bar');
        exit;
    }
}
```

3. Add component class name to dispatcher.  
src/Application.php
```
class Application
{
    ...

    public function dispatch()
    {
        switch (self::$request['trigger_word']) {
            case 'foobar':
                self::run(Components\Foo::class);
                exit;
                break;
        }

        exit;
    }
}
```
