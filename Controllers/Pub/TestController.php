<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;

class TestController extends Controller
{
    public function uploadTest()
    {
        return view('pub.test.upload');
    }
    
    public function thumbnailTest()
    {
        $thumbnailObj = \App::make('App\Image\Thumbnail');
        
        $thumbnailUrl = $thumbnailObj->getThumbnailUrl('A-[***].jpg', 320, 480);
        
        var_dump($thumbnailUrl);
    }
}