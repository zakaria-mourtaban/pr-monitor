<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GithubController extends Controller
{
    // file naming will be as #ofPrs-FileTitle.txt
    // files will be in csv format as PR#, PR Title, PR Url

    // gets prs older than 14 days and writes them to file
    function GetOldPrs($repo){}

    // gets prs with review required
    function GetOpenPrsWithReview($repo){}

    // gets prs where review status is success
    function GetOpenPrsWithSuccess($repo){}

    // get prs without reviews
    function GetOpenPrsWithoutReviews($repo){}


    function LoopThroughList(){}
    
}
