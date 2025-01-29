<?php

use GuzzleHttp\Client;

class GithubService
{
    public function getOldPullRequests()
    {
        $client = new Client();
        $date = now()->subDays(14)->format('Y-m-d');
        $url = "https://api.github.com/search/issues?q=repo:woocommerce/woocommerce+is:pr+is:open+created:<$date";
        $response = $client->get($url, [
            'headers' => ['Authorization' => 'token ' . env('GITHUB_API_TOKEN')]
        ]);
        return json_decode($response->getBody(), true)['items'];
    }
    // Add methods for Goals 2-4 similarly
}
