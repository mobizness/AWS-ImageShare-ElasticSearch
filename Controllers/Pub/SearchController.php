<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Image;
use App\Http\Controllers\Api\V1\SearchController as ApiSearchController;
use Illuminate\Support\Collection;
use App\Models\User;

class SearchController extends Controller
{
    
    public function searchImages(Request $request, ApiSearchController $api)
    {
        $query = $request->get('query', null);
        
        if(empty($query)) {
            return redirect(route('public.galleries'));
        }
        
        try {
            $imageResults = $api->images($request);
        } catch(\Exception $e) {
            \Log::error("Error performing Search: {$e->getMessage()}", ['exception' => $e]);
            return redirect(route('public.galleries'));
        }
        
        return view('pub.search.image-results', compact('imageResults'));
    }
    
    public function searchPeople(Request $request, ApiSearchController $api)
    {
        $query = $request->get('query', null);
        
        if(empty($query)) {
            return redirect(route('auth.account.profile'));
        }
        
        try {
            $peopleResults = $api->people($request);
            
            foreach($peopleResults as &$person) {
            }
        } catch(\Exception $e) {
            throw $e;
            \Log::error("Error performing People Search: {$e->getMessage()}", ['exception' => $e]);
            return redirect(route('auth.account.profile'));
        }
        
        return view('pub.search.people-results', compact('peopleResults'));
    }
}