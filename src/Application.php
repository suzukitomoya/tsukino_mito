<?php
namespace App;

use Dotenv;
use App\Components;

class Application
{
    private static $request;

    function __construct()
    {
        self::$request = [
            'token'        => $_POST['token'] ?? null,
            'channel_id'   => $_POST['channel_id'] ?? null,
            'trigger_word' => $_POST['trigger_word'] ?? null,
            'text'         => $_POST['text'] ?? null,
        ];

        (new Dotenv\Dotenv(__DIR__))->load();
        self::middleware();
    }

    private static function middleware()
    {
        if (
            ! isset(self::$request['token'], self::$request['channel_id'])
            || self::$request['token'] !== getenv('SLACK_WEBHOOK_TOKEN')
            || self::$request['channel_id'] !== getenv('SLACK_CHANNEL_ID')
        ) {
            throw new Exception('Invalid slack credentials');
        }
    }

    private static function run(string $className)
    {
        if (!class_exists($className, false)) {
            throw new Exception('Undefined class: '.$className);
        }
        if (!method_exists($className, 'run')) {
            throw new Exception('Undefined run method: '.$className);
        }

        $className::run(self::$request);
    }

    public function dispatch()
    {
        switch (self::$request['trigger_word']) {
            case '委員長':
            case '月ノ美兎':
                self::run(Components\TsukinoMito::class);
                exit;
                break;

            case ':リリース作成':
                // self::run(Components\CreateGitHubReleaseBranch::class);
                // $repository = strpos($this->request['text'], 'adm_macaroni') === false
                //     ? 'macaroni_web'
                //     : 'adm_macaroni';
                // (new Components\CreateGitHubReleaseBranch($repository))->run();
                exit;
                break;
        }

        exit;
    }
}