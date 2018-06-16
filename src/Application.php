<?php
namespace App;

use Exception;
use App\Components;

class Application
{
    use Response;

    private static $request;

    function __construct()
    {
        self::$request = [
            'token'        => $_POST['token'] ?? null,
            'channel_id'   => $_POST['channel_id'] ?? null,
            'trigger_word' => $_POST['trigger_word'] ?? null,
            'text'         => $_POST['text'] ?? null,
        ];

        self::middleware();
    }

    /**
     * リクエスト検査
     *
     * @return void
     */
    private static function middleware(): void
    {
        try {
            if (
                !isset(self::$request['token'], self::$request['channel_id'])
                || self::$request['token'] !== getenv('SLACK_WEBHOOK_TOKEN')
                || self::$request['channel_id'] !== getenv('SLACK_CHANNEL_ID')
            ) {
                throw new Exception('Invalid slack credentials');
            }
        } catch (Exception $e) {
            error_log('Application::middleware() : '.$e->getMessage());
            self::response('');
            exit;
        }
    }

    /**
     * コンポーネントの実行
     *
     * @param string $className
     * @return void
     */
    private static function run(string $className): void
    {
        try {
            if (!class_exists($className, false)) {
                throw new Exception('Undefined class: '.$className);
            }
            if (!method_exists($className, 'run')) {
                throw new Exception('Undefined run method: '.$className);
            }

            $className::run(self::$request);
        } catch (Exception $e) {
            error_log('Application::run() : '.$e->getMessage());
            self::response('');
            exit;
        }
    }

    /**
     * ディスパッチルーティング
     */
    public function dispatch()
    {
        switch (self::$request['trigger_word']) {
            case '委員長':
            case '月ノ美兎':
                self::run(Components\TsukinoMito::class);
                exit;
                break;
            case ':リリース作成':
                self::run(Components\CreateGitHubReleaseBranch::class);
                exit;
                break;
//            case 'foo':
//                self::run(Components\Bar::class);
//                exit;
//                break;
        }
        exit;
    }
}