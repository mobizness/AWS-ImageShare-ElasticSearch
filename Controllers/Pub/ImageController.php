<?php

namespace App\Http\Controllers\Pub;

use App\Models\Image;
use App\Http\Controllers\Controller;
use App\Models\Image\Thumbnail;
use Cviebrock\EloquentSluggable\Services\SlugService;

class ImageController extends Controller
{
    public function get($id, $dia)
    {
        
    }
    
    public function render($id, $dia, $attachment = null)
    {
        $parts = explode('x', $dia);
        $width = $parts[0];
        $height = $parts[1];
        
        $imageModel = Image::byS3Id($id)->first();
        
        if(!$imageModel instanceof Image) {
            /**
             * @todo return some sort of default broken image data
             */
            return;
        }
        
        $thumbnailModel = $imageModel->iconthumb()
                                     ->byDimensions($width, $height)
                                     ->first();
        
        if(!$thumbnailModel instanceof Thumbnail) {
            $thumbnailModel = Thumbnail::generate($imageModel, $width, $height);    
        }
        
        if($thumbnailModel->isPublic()) {
            $thumbnailModel->save();
            return redirect($thumbnailModel->getUrl());
        }
        
        /**
         * @todo Whatever checks we need to do re. throttling, etc.
         */
        
        $thumbnail = $thumbnailModel->getImagick();
        
        $thumbnailModel->save();
        
        if(strtolower($attachment) != 'a') {
            return response($thumbnail->getImageBlob())
                        ->header('Content-Type', $thumbnail->getImageMimeType());
        }

        $thumbnail->setImageFormat('png');
        
        $slug = SlugService::createSlug(Image::class, 'slug', $imageModel->title);
        $downloadFilename = "$slug.png";
        
        return response($thumbnail->getImageBlob())
                    ->header('Content-Type', $thumbnail->getImageMimeType())
                    ->header('Content-Disposition', "attachment; filename=\"$downloadFilename\"");
    }
}