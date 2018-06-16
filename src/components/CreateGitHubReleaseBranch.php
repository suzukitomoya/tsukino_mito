<?php
namespace App\Components;

use Exception;
use App\Response;
use DateTimeImmutable;
use Github\Client as GithubClient;

/**
 * masterからリリース用ブランチを作成 + master宛にPullRequestを作成するやつ
 */
class CreateGitHubReleaseBranch
{
    use Response;

    private static $now;

    private static $gitRepoOwner;
    private static $githubUserName;
    private static $githubPassword;

    private static $repository;
    private static $githubClient;

    /**
     * @param array $request
     * @throws Exception
     */
    public static function run(array $request)
    {
        self::init($request);

        try {
            $ref = self::createReleaseBranch();
            if (empty($ref)) {
                throw new Exception('リリースブランチの作成に失敗しました。');
            }

            $newRef = self::createEmptyCommit($ref);
            if (empty($newRef)) {
                throw new Exception('空コミットの作成に失敗しました。');
            }

            $pullRequest = self::createReleasePullRequest($newRef);
            if (empty($pullRequest)) {
                throw new Exception('PullRequestの作成に失敗しました。');
            }
        } catch (Exception $e) {
            self::response("失敗しちゃいました！\n".$e->getMessage());
            exit;
        }

        self::response("プルリク作りました！\n".$pullRequest['html_url']);
    }

    /**
     * @param array $request
     * @throws Exception;
     */
    private static function init(array $request)
    {
        self::$now = new DateTimeImmutable();

        self::$gitRepoOwner   = getenv('GIT_REPO_OWNER');
        self::$githubUserName = getenv('GITHUB_USER_NAME');
        self::$githubPassword = getenv('GITHUB_PASSWORD');

        if (!isset(self::$gitRepoOwner, self::$githubUserName, self::$githubPassword)) {
            throw new Exception('CreateGitHubReleaseBranch::init() : .env設定して！');
        }

        self::$repository = $request['text'];

        self::$githubClient = new GithubClient();
        self::$githubClient
            ->authenticate(
                self::$githubUserName,
                self::$githubPassword,
                GithubClient::AUTH_HTTP_PASSWORD
            );
    }

    /**
     * リリースブランチの作成
     *
     * @return array reference
     */
    private static function createReleaseBranch()
    {
        $branches = self::$githubClient
            ->api('gitData')
            ->references()
            ->branches(self::$gitRepoOwner, self::$repository);
        $branchNames = array_column($branches, 'ref');

        // リリースブランチ名の決定
        // 既に同名で存在する場合は、連番で作成する。
        $releaseBranchName   = 'release-'.self::$now->format('Y-m-d');
        $releaseBranchSuffix = '';
        while (1) {
            if (!in_array('refs/heads/'.$releaseBranchName.$releaseBranchSuffix, $branchNames)) {
                break;
            }

            $releaseBranchSuffix = empty($releaseBranchSuffix)
                ? '_2'
                : '_'.((int)ltrim($releaseBranchSuffix, '_') + 1);
        }

        // masterのハッシュ取得
        $masterSHA = null;
        foreach ($branches as $ref) {
            if ($ref['ref'] === 'refs/heads/master') {
                $masterSHA = $ref['object']['sha'];
            }
        }

        // リリースブランチ作成
        $ref = self::$githubClient
            ->api('gitData')
            ->references()
            ->create(self::$gitRepoOwner, self::$repository, [
                'ref' => 'refs/heads/'.$releaseBranchName.$releaseBranchSuffix,
                'sha' => $masterSHA,
            ]);

        return $ref;
    }

    /**
     * 空コミットの作成
     *
     * @param  array $refCommit reference
     * @return array reference
     */
    private static function createEmptyCommit(array $refCommit)
    {
        // 親Commitの取得
        $commit = self::$githubClient
            ->api('gitData')
            ->commits()
            ->show(self::$gitRepoOwner, self::$repository, $refCommit['object']['sha']);

        // 空Commitの作成
        $emptyCommit = self::$githubClient
            ->api('gitData')
            ->commits()
            ->create(self::$gitRepoOwner, self::$repository, [
                'message' => '',
                'tree'    => $commit['tree']['sha'],
                'parents' => [$refCommit['object']['sha']]
            ]);

        // Refの更新
        $headsBranch = str_replace('refs/', '', $refCommit['ref']);
        $ref = self::$githubClient
            ->api('gitData')
            ->references()
            ->update(self::$gitRepoOwner, self::$repository, $headsBranch, [
                'sha'   => $emptyCommit['sha'],
                'force' => false
            ]);

        return $ref;
    }

    /**
     * PullRequestの作成
     *
     * @param  array $refCommit reference
     * @return array pullrequest
     */
    private static function createReleasePullRequest(array $refCommit)
    {
        $branch = str_replace('refs/heads/', '', $refCommit['ref']);
        $pullRequest = self::$githubClient
            ->api('pull_request')
            ->create(self::$gitRepoOwner, self::$repository, [
                'base'  => 'master',
                'head'  => $branch,
                'title' => $branch,
                'body'  => '',
            ]);

        return $pullRequest;
    }
}