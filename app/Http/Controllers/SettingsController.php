<?php
namespace App\Http\Controllers;

use App\Jobs\SyncCustomersJob;
use App\Jobs\SyncOrdersJob;
use App\Services\PancakeApiService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function index()
    {
        $shops    = $this->user()->shops()->orderBy('is_active', 'desc')->get();
        $shop     = $shops->where('is_active', true)->first() ?? $shops->first();
        $syncLogs = $shop?->syncLogs()->latest()->take(10)->get();
        return view('settings.index', compact('shop', 'shops', 'syncLogs'));
    }

    public function detect(Request $request)
    {
        $data   = $request->validate(['api_key' => 'required|string|min:10']);
        $result = PancakeApiService::withKey($data['api_key'])->detectShops();
        return response()->json($result);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'api_key'             => 'required|string',
            'shop_id'             => 'required|string',
            'shop_name'           => 'nullable|string',
            'sync_interval_hours' => 'integer|min:1|max:24',
        ]);

        $this->user()->shops()->update(['is_active' => false]);

        $shop = $this->user()->shops()->updateOrCreate(
            ['shop_id' => $data['shop_id']],
            [
                'api_key'             => $data['api_key'],
                'shop_name'           => $data['shop_name'] ?? $data['shop_id'],
                'is_active'           => true,
                'sync_interval_hours' => $data['sync_interval_hours'] ?? 6,
            ]
        );

        $this->dispatchSync($shop->id, fullSync: true);

        return redirect()->route('settings.index')
            ->with('success', "Connected to \"{$shop->shop_name}\" — sync running in background.");
    }

    public function sync(): RedirectResponse
    {
        $shop = $this->user()->shops()->where('is_active', true)->firstOrFail();
        $this->dispatchSync($shop->id, fullSync: false);
        return back()->with('success', 'Sync started. Check Sync History below — it updates automatically.');
    }

    private function dispatchSync(int $shopId, bool $fullSync = false): void
    {
        $fromDate = null;
        if (!$fullSync) {
            $shop = \App\Models\PancakeShop::find($shopId);
            if ($shop?->last_synced_at) {
                // Look back 7 days so orders whose status changed (e.g. shipped→delivered) get re-fetched
                $fromDate = $shop->last_synced_at->copy()->subDays(7)->format('Y-m-d H:i:s');
            } else {
                // last_synced_at was never set — avoid a full sync that times out; fetch last 30 days only
                $fromDate = now()->subDays(30)->format('Y-m-d H:i:s');
            }
        }

        dispatch(new SyncCustomersJob($shopId));
        dispatch(new SyncOrdersJob($shopId, $fromDate));

        $php     = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $log     = escapeshellarg(storage_path('logs/queue-worker.log'));
        shell_exec("nohup {$php} {$artisan} queue:work --stop-when-empty --timeout=3600 >> {$log} 2>&1 &");
    }
}
