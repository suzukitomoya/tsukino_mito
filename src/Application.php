<?php
namespace App;

use Dotenv;
use App\Components;

class Application
{
    private $request;

    function __construct()
    {
        $this->request = [
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
            ! isset($this->request['token'], $this->request['channel_id'])
            || $this->request['token'] !== getenv('SLACK_WEBHOOK_TOKEN')
            || $this->request['channel_id'] !== getenv('SLACK_CHANNEL_ID')
        ) {
            // TODO: throw Exception
            exit;
        }
    }

    public static function dispatch()
    {
        switch ($this->request['trigger_word']) {
            case '委員長':
            case '月ノ美兎':
                Components\TsukinoMito::run();
                exit;
                break;

            case ':リリース作成':
                $repository = strpos($this->request['text'], 'adm_macaroni') === false
                    ? 'macaroni_web'
                    : 'adm_macaroni';
                (new Components\CreateGitHubReleaseBranch($repository))->run();
                exit;
                break;
        }

        exit;
    }
}