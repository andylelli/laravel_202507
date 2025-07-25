<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Classes\EventCloner;

class EventCloneController extends Controller
{
    /**
     * HTTP API endpoint to clone the demo event for a user.
     * POST /clone-demo-event
     * Expects: demo_event_id and user_id in request.
     */
    public function cloneDemoEvent(Request $request)
    {
        $request->validate([
            'demo_event_id' => 'required|integer|exists:event,event_id',
            'user_id' => 'required|integer|exists:user,user_id',
        ]);

        $cloner = new EventCloner();
        $newEventId = $cloner->cloneEventForUser(
            $request->input('demo_event_id'),
            $request->input('user_id')
        );

        return response()->json([
            'success' => true,
            'new_event_id' => $newEventId,
        ]);
    }
}
