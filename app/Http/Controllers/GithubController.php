<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class GithubController extends Controller
{
    // file naming will be as #ofPrs-FileTitle.txt
    // files will be in csv format as PR#, PR Title, PR Url

    // gets prs older than 14 days and writes them to file
    function GetOldPrs($repo) {}

    // gets prs with review required
    function GetOpenPrsWithReview($repo) {}

    // gets prs where review status is success
    function GetOpenPrsWithSuccess($repo) {}

    // get prs without reviews
    function GetOpenPrsWithoutReviews($repo) {}

    // loop through repo list and execute the 4 functions on each repository
    function LoopThroughList()
    {
        $filePath = base_path('.repolist');

        if (!File::exists($filePath)) {
            return "File does not exist.";
        }

        $lines = File::lines($filePath);

        foreach ($lines as $line) {
            if (!($line == null)) {
                $this->GetOldPrs($line);
                $this->GetOpenPrsWithoutReviews($line);
                $this->GetOpenPrsWithReview($line);
                $this->GetOpenPrsWithSuccess($line);
            }
        }
    }

    function createFile($directory, $name, $text)
    {
        $basePath = base_path("repos/" + $directory);
        $originalDirPath = $basePath;
        $counter = 1;

        while (File::exists($basePath)) {
            $basePath = $originalDirPath . "_$counter";
            $counter++;
        }

        File::makeDirectory($basePath, 0755, true);

        $filePath = $basePath . '/' . $name;

        File::put($filePath, $text);
    }
}
