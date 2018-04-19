<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User\Favorite\Folder;
use Illuminate\Http\Request;
class FavoriteController extends Controller
{
    public function show($id)
    {
        $folder = Folder::currentUser()
                        ->select('id', 'name', 'parent_id')
                        ->where('id', '=', $id)
                        ->limit(1)
                        ->first();
        
        return $folder;
    }
    
    public function index()
    {
        $root = Folder::whereIsRoot()
                      ->select('id', 'name', 'parent_id')
                      ->currentUser()
                      ->get();
        
        return $root->toTree();
    }
    
    public function tree($parentId = null)
    {
        $root = Folder::currentUser()
                      ->get()
                      ->toTree();
                      
        $retval = $root->toArray();
        
        foreach($retval as $key => $val) {
            if(is_null($val['parent_id'])) {
                $retval[$key]['state'] = [
                    'opened' => true
                ];
                break;
            }
        }
        
        return $retval;
    }
    
    public function addFolder(Request $request)
    {
        $this->validate($request, [
            'parent_id' => 'required|integer|exists:user_fav_folders,id',
            'text' => 'required|max:255'
        ]);
        
        $folder = Folder::currentUser()->findOrFail($request->get('parent_id'));
        
        $newFolder = $folder->children()->create([
            'text' => $request->get('text'),
            'user_id' => $folder->user_id
        ]);
        
        return $newFolder;
    }
    
    public function deleteFolder(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer|exists:user_fav_folders,id'
        ]);
        
        $folder = Folder::currentUser()->findOrFail($request->get('id'));
        
        if($folder->isRoot()) {
            return "error";
        }
        
        $folder->delete();
        
        return "ok";
    }
    
    public function renameFolder(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer|exists:user_fav_folders,id',
            'text' => 'required|max:255'
        ]);
        
        $folder = Folder::currentUser()->findOrFail($request->get('id'));
        
        $folder->text = $request->get('text');
        $folder->save();
        
        return 'ok';
    }
}