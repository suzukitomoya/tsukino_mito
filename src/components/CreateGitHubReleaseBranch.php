<?php
namespace App\Components;

use Exception;
use DateTimeImmutable;
use Github\Client as GithubClient;

class CreateGitHubReleaseBranch
{
    use App\Response;

    private $now;

    private $gitRepoOwner;
    private $githubUserName;
    private $githubPassword;

    private $repository;
    private $githubClient;

    function __construct(string $repository)
    {
        $this->now = new DateTimeImmutable();

        $this->gitRepoOwner   = getenv('GIT_REPO_OWNER');
        $this->githubUserName = getenv('GITHUB_USER_NAME');
        $this->githubPassword = getenv('GITHUB_PASSWORD');

        if (!isset($this->gitRepoOwner, $this->githubUserName, $this->githubPassword)) {
            // TODO: throw Exception
            exit;
        }

        $this->repository = $repository;

        $this->githubClient = new GithubClient();
        $this->githubClient
                ->authenticate(
                    $this->githubUserName,
                    $this->githubPassword,
                    GithubClient::AUTH_HTTP_PASSWORD
                );
    }

    /**
     * @return null
     */
    public function run()
    {
        try {
            $ref = $this->createReleaseBranch();
            if (empty($ref)) {
                throw new Exception('リリースブランチの作成に失敗しました。');
            }

            $newRef = $this->createEmptyCommit($ref);
            if (empty($newRef)) {
                throw new Exception('空コミットの作成に失敗しました。');
            }

            $pullRequest = $this->createReleasePullRequest($newRef);
            if (empty($pullRequest)) {
                throw new Exception('PullRequestの作成に失敗しました。');
            }
        }
        catch (Exception $e)
        {
            self::response("失敗しちゃいました！\n".$e->getMessage());
            exit;
        }

        self::response("プルリク作りました！\n".$pullRequest['html_url']);
    }

    /**
     * リリースブランチの作成
     *
     * @return array reference
     */
    private function createReleaseBranch()
    {
        $branches = $this->githubClient
                            ->api('gitData')
                            ->references()
                            ->branches($this->gitRepoOwner, $this->repository);
        $branchNames = array_column($branches, 'ref');

        // リリースブランチ名の決定
        // 既に同名で存在する場合は、連番で作成する。
        $releaseBranchName   = 'release-'.$this->now->format('Y-m-d');
        $releaseBranchSuffix = '';
        while (1) {
            if (in_array('refs/heads/'.$releaseBranchName.$releaseBranchSuffix, $branchNames)) {
                $releaseBranchSuffix = empty($releaseBranchSuffix)
                                            ? '_2'
                                            : '_'.((int)ltrim($releaseBranchSuffix, '_') + 1);
            } else {
                break;
            }
        }

        // masterのハッシュ取得
        $masterSHA = null;
        foreach ($branches as $ref) {
            if ($ref['ref'] === 'refs/heads/master') {
                $masterSHA = $ref['object']['sha'];
            }
        }

        // リリースブランチ作成
        $ref = $this->githubClient
                    ->api('gitData')
                    ->references()
                    ->create($this->gitRepoOwner, $this->repository, [
                        'ref' => 'refs/heads/'.$releaseBranchName.$releaseBranchSuffix,
                        'sha' => $masterSHA,
                    ]);

        return $ref;
    }

    /**
     * 空コミットの作成
     *
     * @param  array reference
     * @return array reference
     */
    private function createEmptyCommit(array $refCommit)
    {
        // 親Commitの取得
        $commit = $this->githubClient
                        ->api('gitData')
                        ->commits()
                        ->show($this->gitRepoOwner, $this->repository, $refCommit['object']['sha']);

        // 空Commitの作成
        $emptyCommit = $this->githubClient
                            ->api('gitData')
                            ->commits()
                            ->create($this->gitRepoOwner, $this->repository, [
                                'message' => '',
                                'tree'    => $commit['tree']['sha'],
                                'parents' => [$refCommit['object']['sha']]
                            ]);

        // Refの更新
        $headsBranch = str_replace('refs/', '', $refCommit['ref']);
        $ref = $this->githubClient
                    ->api('gitData')
                    ->references()
                    ->update($this->gitRepoOwner, $this->repository, $headsBranch, [
                        'sha'   => $emptyCommit['sha'],
                        'force' => false
                    ]);

        return $ref;
    }

    /**
     * PullRequestの作成
     *
     * @param  array reference
     * @return array pullrequest
     */
    private function createReleasePullRequest(array $refCommit)
    {
        $branch = str_replace('refs/heads/', '', $refCommit['ref']);
        $pullRequest = $this->githubClient
                            ->api('pull_request')
                            ->create($this->gitRepoOwner, $this->repository, [
                                'base'  => 'master',
                                'head'  => $branch,
                                'title' => $branch,
                                'body'  => '',
                            ]);

        return $pullRequest;
    }
}