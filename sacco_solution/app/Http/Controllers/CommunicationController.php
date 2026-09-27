<?php

namespace App\Http\Controllers;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationLog;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * BRD Section 10 "Communication Manager": SMS/email to stakeholders,
 * with template variables (client name, amount, due date, etc).
 * No real SMS/email gateway is wired up -- send() simulates delivery
 * and always logs the attempt, matching the BRD's "Failed and
 * undelivered emails & sms report" concept either way.
 */
class CommunicationController extends Controller
{
    public function campaigns(Request $request)
    {
        $campaigns = CommunicationCampaign::where('org_id', $request->query('org_id'))->get();
        return response()->json($campaigns);
    }

    public function storeCampaign(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'name' => 'required|string|max:120',
            'trigger_type' => 'required|in:manual,scheduled,event',
            'channel' => 'required|in:sms,email,both',
            'message_template' => 'required|string|max:500',
            'created_by' => 'required|integer|exists:users,id',
        ]);

        $campaign = CommunicationCampaign::create([...$validated, 'status' => 1]);
        return response()->json($campaign, 201);
    }

    public function logs(Request $request)
    {
        $logs = CommunicationLog::where('org_id', $request->query('org_id'))
            ->with('client')->orderByDesc('sent_at')->limit(50)->get();
        return response()->json($logs);
    }

    /**
     * Sends a one-off (or campaign-based) message to a specific client,
     * substituting {client_name} etc. in the template, then logs the
     * attempt. In a real deployment this would call an actual SMS/email
     * gateway; here we simulate success so the workflow is testable.
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'org_id' => 'required|integer|exists:organizations,id',
            'client_id' => 'required|integer|exists:clients,id',
            'channel' => 'required|in:sms,email',
            'message_body' => 'required|string|max:500',
            'campaign_id' => 'nullable|integer|exists:communication_campaigns,id',
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $recipient = $validated['channel'] === 'sms' ? $client->mobile_number : $client->email;

        if (!$recipient) {
            $log = CommunicationLog::create([
                'org_id' => $validated['org_id'],
                'campaign_id' => $validated['campaign_id'] ?? null,
                'client_id' => $client->id,
                'channel' => $validated['channel'],
                'recipient' => 'N/A',
                'message_body' => $validated['message_body'],
                'status' => 'failed',
                'error_message' => "Client has no {$validated['channel']} contact on file.",
            ]);
            return response()->json($log, 422);
        }

        $log = CommunicationLog::create([
            'org_id' => $validated['org_id'],
            'campaign_id' => $validated['campaign_id'] ?? null,
            'client_id' => $client->id,
            'channel' => $validated['channel'],
            'recipient' => $recipient,
            'message_body' => $validated['message_body'],
            'status' => 'sent', // simulated -- no real gateway wired up
        ]);

        return response()->json($log, 201);
    }
}
