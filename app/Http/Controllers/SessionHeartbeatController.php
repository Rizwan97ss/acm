<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Keep-alive for the idle timer. The browser calls this while someone is active on a page
 * without navigating (typing a long form, reading a report). Passing through the web
 * middleware refreshes the session's last-activity time; a timed-out session is signed
 * out by EnforceAccountSecurity before this runs.
 */
class SessionHeartbeatController extends Controller
{
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
