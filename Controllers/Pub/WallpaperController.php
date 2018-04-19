<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Image;
use App\Http\Controllers\Api\V1\ImageController;


class WallpaperController extends Controller
{
    public function display(Request $request, ImageController $api, $wallpaperId)
    {
        $wallpaper = Image::newFromStd($api->show($request, $wallpaperId)->getData());
        return view('pub.wallpaper.display', compact('wallpaper'));
    }
    
    public function upload()
    {
        return view('pub.wallpaper.upload');
    }
}