<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SampleController extends Controller
{
    /**
     * Controller for retrieving user-visible announcements.
     * - Accessed from the Quick Menu
     * - Includes announcements from the development team
     * - Includes top account announcements filtered by user group
     */
    public function index()
    {
        return view('announcement.index');
    }

    public function getTopAnnounces(Request $request)
    {
        $date   = Carbon::now();

        $start  = $date->copy()->subDay(1)->startOfDay()->format('Y-m-d H:i:s');
        $end    = $date->copy()->endOfDay()->format('Y-m-d H:i:s');

        // Sample query for testing:
        $user           = Auth::user();
        $myAccountId    = $user->account->id;
        $topAccountId   = $user->account->top_account_id;
        $userRoleId     = $user->role->id;

        /******************************************************************
         * Query to retrieve top announcements
         *
         * 1) Announcements written by the development team have account_id = NULL
         *
         * 2) Announcements created by the current organization use the
         *    top_account_id as account_id
         ******************************************************************/
        $announces = Announcement::whereBetween('created_at', [$start, $end])
            ->whereFixYn()
            ->whereUseYn()
            ->latest()
            ->where(function($query) use ($myAccountId, $topAccountId, $userRoleId) {
                // 1. Include global announcements (account_id is NULL)
                $query->whereNull('account_id')

                    // 2. Include user-specific announcements (account_id = user's own account)
                    ->orWhere('account_id', $myAccountId)

                    // 3. Include top account announcements if the user's role is in board_group
                    ->orWhere(function($query) use ($topAccountId, $userRoleId) {
                        $query->where('account_id', $topAccountId)
                            ->where(function($query) use ($userRoleId) {
                                // Check if user's role_id is included in board_group
                                $query->whereRaw("FIND_IN_SET(?, board_group)", [$userRoleId]);
                            });
                    });
            })
            ->take(5) // Limit to the latest 5 announcements
            ->get();

        return response()->json([
            'success' => true,
            'data' => collect($announces),
            'message' => translate('MSG.SUCCESS')
        ]);
    }
}
