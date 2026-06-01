<?php

namespace App\Http\Controllers;

use App\Notifications\TestPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Sla een browser push-subscription op voor de ingelogde gebruiker.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aesgcm',
        );

        return response()->json(['status' => 'subscribed']);
    }

    /**
     * Verwijder een subscription (gebruiker schakelt push uit op dit device).
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * Stuur een test-notificatie naar de ingelogde gebruiker.
     */
    public function test(Request $request): JsonResponse
    {
        $request->user()->notify(new TestPushNotification);

        return response()->json(['status' => 'sent']);
    }
}
