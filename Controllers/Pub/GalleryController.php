<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\GalleryController as ApiGalleryController;
use Illuminate\Support\Collection;
use App\Models\Gallery;

class GalleryController extends Controller
{
    public function displayAll(Request $request, ApiGalleryController $api)
    {
        $galleries = new Collection();
        
        foreach($api->index() as $galleryData) {
            $galleries->push(Gallery::newFromStd($galleryData));                
        }
        
        return view('pub.galleries.all', compact('galleries'));
    }
    
    public function display(Request $request, ApiGalleryController $api, $id)
    {
        $gallery = $api->show($id);
        
        if(empty($gallery)) {
            abort(404);
        }
        
        $gallery = Gallery::newFromStd($gallery);
        
        $galleryImages = $gallery->getImagesQuery()->paginate(32);
        
        return view('pub.galleries.view', compact('gallery', 'galleryImages'));
    }
}