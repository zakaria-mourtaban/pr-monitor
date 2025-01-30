<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;

class GithubController extends Controller
{
    // Gets PRs older than 14 days and writes them to file
    function GetOldPrs($repo)
    {
        $client = new Client(['verify' => false]);
        $url = "https://api.github.com/repos/{$repo}/pulls";
        $oldPrs = [];
        $threshold = Carbon::now()->subDays(14);

        try {
            $response = $client->get($url, [
                'headers' => $this->getHeaders(),
                'query' => ['state' => 'open']
            ]);
            
            $prs = json_decode($response->getBody(), true);
            
            foreach ($prs as $pr) {
                if (Carbon::parse($pr['created_at'])->lt($threshold)) {
                    $oldPrs[] = $this->formatPrData($pr);
                }
            }

            $this->createOutputFile($repo, $oldPrs, 'OldPRs');
            return $oldPrs;

        } catch (RequestException $e) {
            return $this->handleError($e);
        }
    }

    // Gets PRs with review required
    function GetOpenPrsWithReview($repo)
    {
        return $this->searchPrs($repo, 'review:required', 'NeedsReview');
    }

    // Gets PRs where review status is success
    function GetOpenPrsWithSuccess($repo)
    {
        return $this->searchPrs($repo, 'status:success', 'ApprovedPRs');
    }

    // Gets PRs without reviews
    function GetOpenPrsWithoutReviews($repo)
    {
        return $this->searchPrs($repo, 'review:none', 'NoReviews');
    }

    // Helper method for PR searches
    private function searchPrs($repo, $qualifier, $fileName)
    {
        $client = new Client(['verify' => false]);
        $query = urlencode("repo:{$repo} is:pr is:open {$qualifier}");

        try {
            $response = $client->get("https://api.github.com/search/issues?q={$query}", [
                'headers' => $this->getHeaders()
            ]);

            $prs = json_decode($response->getBody(), true)['items'];
            $formattedPrs = array_map([$this, 'formatPrData'], $prs);

            $this->createOutputFile($repo, $formattedPrs, $fileName);
            return $formattedPrs;

        } catch (RequestException $e) {
            return $this->handleError($e);
        }
    }

    // Common headers for GitHub API
    private function getHeaders()
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . env('GITHUB_API_TOKEN'),
        ];
    }

    // Format PR data for CSV
    private function formatPrData($pr)
    {
        return [
            'number' => $pr['number'],
            'title' => $pr['title'],
            'url' => $pr['html_url']
        ];
    }

    // Create output file with PR data
    private function createOutputFile($repo, $prs, $type)
    {
        if (empty($prs)) return;

        $csv = "PR#, PR Title, PR Url\n";
        foreach ($prs as $pr) {
            $csv .= sprintf("%d, \"%s\", %s\n", $pr['number'], $pr['title'], $pr['url']);
        }

        $sanitizedRepo = str_replace('/', '_', $repo);
        $fileName = count($prs) . "-{$type}.csv";
        $this->createFile($sanitizedRepo, $fileName, $csv);
    }

    // Handle API errors
    private function handleError($e)
    {
        if ($e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorMessage = "API request failed: Status {$statusCode}";
        } else {
            $errorMessage = "API request failed: " . $e->getMessage();
        }

        logger()->error($errorMessage);
        return [];
    }

    // Create directory and file with unique naming
    function createFile($directory, $name, $text)
    {
        $baseDir = base_path("repos/{$directory}");
        $finalDir = $baseDir;

        File::makeDirectory($finalDir, 0755, true);
        File::put("{$finalDir}/{$name}", $text);
    }

    // Loop through repo list and execute checks
    function LoopThroughList()
    {
        $filePath = base_path('.repolist');

        if (!File::exists($filePath)) {
            logger()->error('Repo list file not found');
            return;
        }

        File::lines($filePath)->each(function ($repo) {
            if (trim($repo)) {
                $this->GetOldPrs($repo);
                $this->GetOpenPrsWithoutReviews($repo);
                $this->GetOpenPrsWithReview($repo);
                $this->GetOpenPrsWithSuccess($repo);
            }
        });
    }
}