<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;

class GithubController extends Controller
{
    public $remaining = 99;

    public function GetOldPrs($repo)
    {
        $client = new Client([
            'verify' => false,
            'http_errors' => true
        ]);
        $url = "https://api.github.com/repos/{$repo}/pulls";
        $oldPrs = [];
        $threshold = Carbon::now()->subDays(14);

        try {
            $message = "Starting OldPRs fetch for {$repo}";
            logger()->info($message);
            echo "ℹ️ $message\n";

            $response = $client->get($url, [
                'headers' => $this->getHeaders(),
                'query' => ['state' => 'open']
            ]);

            $rateLimitRemaining = $response->getHeader('X-RateLimit-Remaining')[0] ?? 'unknown';
            $this->remaining = $rateLimitRemaining;
            $message = "OldPRs success for {$repo}. Remaining calls: {$rateLimitRemaining}";
            logger()->info($message);
            echo "✅ $message\n";

            $prs = json_decode($response->getBody(), true);
            foreach ($prs as $pr) {
                if (Carbon::parse($pr['created_at'])->lt($threshold)) {
                    $oldPrs[] = $this->formatPrData($pr);
                }
            }
            $this->createOutputFile($repo, $oldPrs, 'OldPRs');
            return $oldPrs;
        } catch (RequestException $e) {
            return $this->handleError($e, $repo, $url);
        }
    }

    public function GetOpenPrsWithReview($repo)
    {
        return $this->searchPrs($repo, 'review:required', 'NeedsReview');
    }

    public function GetOpenPrsWithSuccess($repo)
    {
        return $this->searchPrs($repo, 'status:success', 'ApprovedPRs');
    }

    public function GetOpenPrsWithoutReviews($repo)
    {
        return $this->searchPrs($repo, 'review:none', 'NoReviews');
    }

    private function searchPrs($repo, $qualifier, $fileName)
    {
        $client = new Client([
            'verify' => false,
            'http_errors' => true
        ]);
        $query = urlencode("repo:{$repo} is:pr is:open {$qualifier}");
        $url = "https://api.github.com/search/issues?q={$query}";

        try {
            $message = "Starting {$fileName} fetch for {$repo}";
            logger()->info($message);
            echo "ℹ️ $message\n";

            $response = $client->get($url, [
                'headers' => $this->getHeaders()
            ]);

            $rateLimitRemaining = $response->getHeader('X-RateLimit-Remaining')[0] ?? 'unknown';
            $this->remaining = $rateLimitRemaining;
            $message = "{$fileName} success for {$repo}. Remaining calls: {$rateLimitRemaining}";
            logger()->info($message);
            echo "✅ $message\n";

            $prs = json_decode($response->getBody(), true)['items'];
            $formattedPrs = array_map([$this, 'formatPrData'], $prs);

            $this->createOutputFile($repo, $formattedPrs, $fileName);
            return $formattedPrs;
        } catch (RequestException $e) {
            return $this->handleError($e, $repo, $url);
        }
    }

    private function handleError($e, $repo = null, $url = null)
    {
        $errorMessage = $e->getMessage();
        $context = [
            'repo' => $repo,
            'url' => $url,
            'error' => $errorMessage
        ];

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $context['status_code'] = $statusCode;

            if ($response->hasHeader('X-RateLimit-Remaining')) {
                $rateLimit = $response->getHeader('X-RateLimit-Remaining')[0];
                $context['rate_limit'] = $rateLimit;
                $message = "API Error ({$statusCode}) for {$repo} | Remaining calls: {$rateLimit} | {$errorMessage}";
            } else {
                $message = "API Error ({$statusCode}) for {$repo}: {$errorMessage}";
            }

            logger()->error($message, $context);
            echo "🔴 $message\n";
        } else {
            $message = "Connection Error for {$repo}: {$errorMessage}";
            logger()->error($message, $context);
            echo "🔴 $message\n";
        }

        return [];
    }

    private function getHeaders()
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . env('GITHUB_API_TOKEN'),
        ];
    }

    private function formatPrData($pr)
    {
        return [
            'number' => $pr['number'],
            'title' => $pr['title'],
            'url' => $pr['html_url']
        ];
    }

    private function createOutputFile($repo, $prs, $type)
    {
        $csv = "PR#, PR Title, PR Url\n";
        foreach ($prs as $pr) {
            $csv .= sprintf("%d, \"%s\", %s\n", $pr['number'], $pr['title'], $pr['url']);
        }

        $sanitizedRepo = str_replace('/', '_', $repo);
        $fileName = count($prs) . "-{$type}.csv";
        $this->createFile($sanitizedRepo, $fileName, $csv);
    }

    private function createFile($directory, $name, $text)
    {
        $baseDir = base_path("repos/{$directory}");
        File::ensureDirectoryExists($baseDir, 0755);
        File::put("{$baseDir}/{$name}", $text);
        $message = "Created file: {$directory}/{$name}";
        logger()->info($message);
        echo "📁 $message\n";
    }

    public function LoopThroughList()
    {
        $filePath = base_path('.repolist');

        //check if the list exists
        if (!File::exists($filePath)) {
            $message = 'Repo list file not found at ' . $filePath;
            logger()->error($message);
            echo "🔴 $message\n";
            return;
        }

        //loop over each line in the file
        File::lines($filePath)->each(function ($repoLine) {
            //trim extra spacing
            $repo = trim($repoLine);
            if (!empty($repo)) {
                $message = "🚀 Processing repository: {$repo}";
                logger()->info($message);
                echo "$message\n";

                try {
                    //check the limit
                    if ($this->remaining <= 5) {
                        echo "⚠️ Ratelimit has been reached, sleeping for 30 seconds";
                        sleep(30);
                    }
                    $this->GetOldPrs($repo);
                    $this->GetOpenPrsWithoutReviews($repo);
                    $this->GetOpenPrsWithReview($repo);
                    $this->GetOpenPrsWithSuccess($repo);
                } catch (\Exception $e) {
                    $message = "💥 Critical error processing {$repo}";
                    logger()->error($message, [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    echo "🔴 $message: {$e->getMessage()}\n";
                }

                $message = "✅ Finished processing: {$repo}";
                logger()->info($message);
                echo "$message\n\n";
            }
        });
    }
}
