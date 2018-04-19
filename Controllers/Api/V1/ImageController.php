<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Aws\S3\S3Client;
use Aws\S3\PostObjectV4;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;
use App\Models\Image\Thumbnail;
use App\Util\StringUtils;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use App\Models\Post;
use App\Events\Posting;
class ImageController extends Controller
{
    protected $_s3Client;
    
    public function __construct(S3Client $client) {
        $this->_s3Client = $client;
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $wallpaper = Image::byS3IdOrFail($id);
        return response()->json($wallpaper);
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $image = Image::byS3IdOrFail($id);
        
        if($image->uploader->id != \Auth::user()->id) {
            /**
             * @todo Permission check here to allow other than owner to edit
             */
            return $this->unauthorized();
        }
        
        $image->delete();
        
        return response()->json('ok');
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $image = Image::byS3IdOrFail($id);
        
        if($image->uploader->id != \Auth::user()->id) {
            /**
             * @todo Permission check herer to allow other than owner to edit
             */
            return $this->unauthorized();
        }
        
        $this->validate($request, [
            'title' => 'required|max:255',
            'groups' => 'sometimes|valid_image_groups',
            'tags' => 'sometimes|valid_tags'
        ]);
        
        $image->title = $request->get('title');
        
        if($request->has('groups')) {
            /**
             * @todo Process groups
             */
        }
        
        if($request->has('tags')) {
            
            $tags = explode(',', $request->get('tags'));

            \DB::transaction(function() use ($image, $tags) {
                $image->tags()->detach();
                
                foreach($tags as $tag) {
                    $image->addTag($tag);
                }
                
            });
            
        }
        
        \DB::transaction(function() use ($image) {
            $image->save();
            
        });
        
        return response()->json($image);
    }
    
    public function uploadCredentials()
    {
        $options = [
            ['bucket' => config('services.aws.s3.upload_bucket')],
            ['starts-with', '$key', '']
        ];

        $postObject = new PostObjectV4(
            $this->_s3Client, 
            config('services.aws.s3.upload_bucket'),
            [],
            $options,
            '+15 minutes'
        );
        
        return [
            'attributes' => $postObject->getFormAttributes(),
            'additionalData' => $postObject->getFormInputs()
        ];
    }
    
    public function processUploadedFile(Request $request)
    {
        $retval = [
            'success' => false
        ];
        
        $imageName = $request->input('name');
        
        try {

            $image = Storage::disk('uploads')->get($imageName);
            
            $imagick = new \Imagick();
            $imagick->readImageBlob($image);
            
            /**
             * @todo do any additional validation of the image data here
             */
            
            $imageModel = new Image();
            $imageModel->title = StringUtils::titleFromFilename($imageName);
            $imageModel->description = $imageModel->title;
            $imageModel->upload_by = $request->user()->id;
            $imageModel->upload_date = Carbon::now();
            $imageModel->orig_width = $imagick->getImageWidth();
            $imageModel->orig_height = $imagick->getImageHeight();
            $imageModel->file_size = $imagick->getImageLength();
            $imageModel->s3_id = md5($imageName . uniqid());
            
            // Copy the uploaded file to the "managed" s3 bucket
            $s3Client = Storage::disk('uploads')
                               ->getDriver()
                               ->getAdapter()
                               ->getClient();
            
            $s3Client->copyObject([
                'Bucket' => config('filesystems.disks.iconthumb.bucket'),
                'Key' => $imageModel->s3_id,
                'CopySource' => config('filesystems.disks.uploads.bucket') . '/' . $imageName
            ]);
            
            Storage::disk('uploads')->delete($imageName);
            
            try { 
                $imageModel->save();
            } catch(\Exception $e) {
                // Clean up the iconthumb copy if we can't save the model for some reason
                Storage::disk('iconthumb')->delete($imageModel->s3_id);
                throw $e;
            }
            
            try {
                $postEvent = new Posting();
                $postEvent->setAuthor($imageModel->uploader)
                          ->setImage($imageModel)
                          ->publishToFacebook(true)
                          ->publishToTwitter(true)
                          ->setType(Post::TYPE_UPLOAD);
                
                event($postEvent);
                
            } catch(\Exception $e) {
                \Log::error($e->getMessage());
            }
            
            /**
             * @todo if we need to pre-generate a thumbnail, this is a good place to do it.
             */
            
            $retval = [
                'success' => true,
                'id' => $imageModel->s3_id
            ];
            
        } catch(FileNotFoundException $e) {
            $retval['success'] = false;
            
            if(\App::environment() != 'production') {
                $retval['error'] = "Could not locate the specified uploaded file";
            }
            
        } catch(\Exception $e) {
            \Log::error("Failed to process uploaded file: {$e->getMessage()}", ['exception' => $e]);
            $retval['success'] = false;
            
            if(\App::environment() != 'production') {
                $retval['error'] = $e->getMessage();
            }
            
        } finally {
            return response()->json($retval);
        }
        
    }
    
    public function share(Request $request, $id, $type)
    {
        $image = Image::byS3IdOrFail($id);
    }
    
    public function toggleLike($id)
    {
        $image = Image::byS3IdOrFail($id);
    
        if($image->isLiked(\Auth::user())) {
            \Auth::user()->unlike($image);
            return "off";
        } else {
            \Auth::user()->like($image);
            return "on";
        }
    }
}