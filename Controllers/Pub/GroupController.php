<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\GroupController as ApiGroupController;
use Illuminate\Http\Request;
use App\Models\Group;

class GroupController extends Controller
{
    public function display(Request $request, ApiGroupController $api, $id)
    {
        $groupData = $api->show($id);
        
        if(empty($groupData)) {
            abort(404);
        }
        
        $group = Group::newFromStd($groupData);
        $groupImages = $group->images()->orderBy('created_at', 'desc')->paginate(32);
        
        return view('pub.groups.view', compact('group', 'groupImages'));
    }
}