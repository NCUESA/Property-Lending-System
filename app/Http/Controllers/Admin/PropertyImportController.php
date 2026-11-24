<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PropertyCsv;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * 批量匯入器材：上傳 CSV → 預覽每一列會新增、更新或有錯 → 確認後才寫入。
 * 預覽結果暫存 30 分鐘，確認時直接套用，不重新上傳。
 */
class PropertyImportController extends Controller
{
    private const TTL_MINUTES = 30;

    public function create(Request $request): Response
    {
        $token = $request->string('token')->toString();
        $preview = $token !== '' ? $this->load($request, $token) : null;

        return Inertia::render('Admin/Properties/Import', [
            'token' => $preview !== null ? $token : null,
            'rows' => $preview !== null
                ? array_map(fn (array $r) => array_diff_key($r, ['attributes' => true]), $preview)
                : null,
            'columns' => array_keys(PropertyCsv::COLUMNS),
            'maxRows' => PropertyCsv::MAX_ROWS,
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:2048'],
        ], [], ['file' => 'CSV 檔']);

        try {
            $rows = PropertyCsv::preview((string) $request->file('file')?->getRealPath());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => '檔案裡沒有資料列。']);
        }

        $token = (string) Str::uuid();
        Cache::put($this->key($token), ['user_id' => $request->user()?->id, 'rows' => $rows], now()->addMinutes(self::TTL_MINUTES));

        return to_route('admin.properties.import.create', ['token' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $rows = $this->load($request, $data['token']);

        if ($rows === null) {
            throw ValidationException::withMessages(['file' => '預覽已過期，請重新上傳。']);
        }

        try {
            $result = PropertyCsv::apply($rows);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        Cache::forget($this->key($data['token']));
        Toast::success("匯入完成：新增 {$result['created']} 件、更新 {$result['updated']} 件。");

        return to_route('admin.properties.index');
    }

    /** @return list<array<string, mixed>>|null */
    private function load(Request $request, string $token): ?array
    {
        $cached = Cache::get($this->key($token));

        // 只有上傳的人能看預覽、確認匯入
        if (! is_array($cached) || $cached['user_id'] !== $request->user()?->id) {
            return null;
        }

        return $cached['rows'];
    }

    private function key(string $token): string
    {
        return "property-import:{$token}";
    }
}
