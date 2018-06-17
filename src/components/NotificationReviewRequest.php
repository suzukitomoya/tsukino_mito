<?php
namespace App\Components;

use App\Response;
use Github\Client as GithubClient;

/**
 * 未レビューのPRを通知してくれるやつ
 */
class NotificationReviewRequest
{
    use Response;

    private static $gitRepoOwner;
    private static $gitRepository;
    private static $githubClient;

    /**
     * @return void
     */
    private static function init(array $request): void
    {
        $githubUserName = getenv('GITHUB_USER_NAME');
        $githubPassword = getenv('GITHUB_PASSWORD');

        self::$gitRepoOwner  = getenv('GIT_REPO_OWNER');
        self::$gitRepository = trim(str_replace($request['trigger_word'], '', $request['text']));
        if (empty(self::$gitRepository)) {
            self::response('リポジトリ名を指定してください！');
            exit;
        }

        self::$githubClient = new GithubClient();
        self::$githubClient
            ->authenticate($githubUserName, $githubPassword, GithubClient::AUTH_HTTP_PASSWORD);
    }

    /**
     * @param array $request
     */
    public static function run(array $request)
    {
        self::init($request);

        $openPullRequests = self::$githubClient
            ->api('pull_request')
            ->all(self::$gitRepoOwner, self::$gitRepository, ['state' => 'open']);

        // 通知メッセージ(未レビューPR)があるときだけ通知します
        $message = self::getMessage($openPullRequests);
        if (!empty($message)) {
            self::response($message);
        }
        exit;
    }

    /**
     * 通知メッセージの組み立て
     *
     * @param array $pullRequests
     * @return string
     */
    private static function getMessage(array $pullRequests): string
    {
        $body = '';
        foreach ($pullRequests as $pr) {
            if (empty($pr['requested_reviewers'])) {
                continue;
            }

            $body .= ':beer:' . self::escapeString($pr['title']) . "\n"
                . self::escapeString(
                    '@' . implode(' @', array_column($pr['requested_reviewers'], 'login'))
                ) . "\n"
                . $pr['html_url'] . "\n\n\n";
        }

        return empty($body)
            ? ''
            : sprintf(
                ":innocent::innocent::innocent: [%s] レビュー依頼が届いてるよ〜 :innocent::innocent::innocent:\n\n\n%s",
                self::$gitRepository,
                $body
            );
    }

    /**
     * 文字列のエスケープ
     *
     * @param string $text
     * @return string
     */
    private static function escapeString(string $text): string
    {
        // CRLF以外の制御文字削除
        $text = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = htmlspecialchars($text, ENT_QUOTES, 'utf-8');

        return $text;
    }
}