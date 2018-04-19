<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Image;
use App\Plastic\Fillers\ReloadEloquentFiller;

class SearchController extends \App\Http\Controllers\Controller
{
    public function people(Request $request)
    {
        $name = $request->get('name', $request->get('query', null));
        $first_name = $request->get('first_name', null);
        $last_name = $request->get('last_name', null);
        $nickname = $request->get('nickname', null);
        $pageSize = $request->get('page_size', 10);
        
        $searchQuery = User::search();
                           
        $searchQuery->setModelFiller(new ReloadEloquentFiller());
        
        if(is_null($name) && is_null($first_name) && is_null($last_name) && is_null($nickname)) {
            throw new \Exception("Invalid Query");
        }
        
        if(!is_null($name)) {
            $searchQuery->should()
                            ->match('name', $name);
        }
        
        if(!is_null($first_name)) {
            $searchQuery->should()
                            ->match('first_name', $first_name);
        }
        
        if(!is_null($last_name)) {
            $searchQuery->should()
                            ->match('last_name', $last_name);
        }
        
        if(!is_null($nickname)) {
            $searchQuery->should()
                            ->match('nickname', $nickname);
        }
        
        $results = $searchQuery->paginate($pageSize);
        
        return $results;
    }
    
    public function groups(Request $request)
    {
        
    }
    
    public function images(Request $request)
    {
        $query = $request->get('query', null);
        $pageSize = $request->get('page_size', 25);
        
        if(empty($query)) {
            throw new \Exception("Invalid Query");
        }
        
        $images = Image::search()
                        ->multiMatch(['title', 'description'], $query)
                        ->paginate($pageSize);
        
        return $images;
    }
    
    public function publicSearch(Request $request)
    {
        $query = $request->get('query');
        $pageSize = $request->get('page_size', 25);
        
        if(empty($query)) {
            throw new \Exception("Invalid Query");
        }
        
        $images = Image::search()
                       ->multiMatch(['title', 'description'], $query)
                       ->paginate($pageSize);
        
        return $images;
                        
    }
    
    public function search(Request $request)
    {
        
    }
    
}