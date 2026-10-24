<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Client\BlueprintClientLibrary;

/*
 * GET /api/client/extensions/serverorder/order
 *
 * Returns the saved order as server short IDs (the IDs used in dashboard links).
 * Only servers the signed-in user can access are included: their own servers,
 * servers they are a subuser of, and every server for root admins. Other servers
 * are left out, so the response never reveals servers the user can't see.
 */
Route::get('/order', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return response()->json(['order' => []], 401);
    }

    $stored = app(BlueprintClientLibrary::class)->dbGet('serverorder', 'order', []);
    $uuids = is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];

    if (empty($uuids)) {
        return response()->json(['order' => []]);
    }

    $servers = Server::query()->whereIn('uuid', $uuids);

    if (!$user->root_admin) {
        $sharedServerIds = DB::table('subusers')
            ->where('user_id', $user->id)
            ->pluck('server_id')
            ->all();

        $servers->where(function ($query) use ($user, $sharedServerIds) {
            $query->where('owner_id', $user->id)
                ->orWhereIn('id', $sharedServerIds);
        });
    }

    $identifiers = $servers->pluck('uuidShort', 'uuid');

    $order = [];
    foreach ($uuids as $uuid) {
        if (isset($identifiers[$uuid])) {
            $order[] = $identifiers[$uuid];
        }
    }

    return response()->json(['order' => $order]);
});
