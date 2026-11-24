<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BorrowItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyRequest;
use App\Models\BorrowRequestItem;
use App\Models\BorrowRequestOverview;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Support\AuthLevel;
use App\Support\PropertyImage;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    /** 每頁筆數的可選值，第一個是預設值。前端的下拉選單也用這份清單。 */
    public const PER_PAGE_OPTIONS = [10, 30, 50, 100];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'location_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            // 品項池 id，或 none = 只看不屬於任何品項池的器材
            'pool_id' => ['nullable', 'regex:/^(none|\d+)$/'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $properties = Property::query()
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('ssid', 'ilike', "%{$term}%")
                ->orWhere('name', 'ilike', "%{$term}%")
                ->orWhere('second_name', 'ilike', "%{$term}%")
                ->orWhere('custodian', 'ilike', "%{$term}%")))
            ->when($filters['location_id'] ?? null, fn (Builder $q, int $id) => $q->where('location_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('subcategory', fn (Builder $s) => $s->where('property_category_id', $id)))
            ->when($filters['pool_id'] ?? null, fn (Builder $q, string $pool) => $pool === 'none'
                ? $q->whereNull('property_pool_id')
                : $q->where('property_pool_id', (int) $pool))
            ->with(['subcategory.category:id,code,name', 'location:id,name', 'pool:id,name', 'activeBorrowItem:id,property_id,status,borrow_request_id'])
            ->orderBy('ssid')
            ->paginate($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0])
            ->withQueryString()
            ->through(fn (Property $p) => $p->setAttribute('image_url', PropertyImage::url($p->image_path)));

        return Inertia::render('Admin/Properties/Index', [
            'filters' => $filters,
            'properties' => $properties,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            ...$this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Properties/Form', [
            'property' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(PropertyRequest $request): RedirectResponse
    {
        $property = new Property($request->safe()->except(['image', 'remove_image']));
        $property->image_path = $this->storeImage($request->file('image'));
        $property->save();

        Toast::success("已新增 {$property->ssid} {$property->name}。");

        return to_route('admin.properties.index');
    }

    /**
     * 器材反查：目前在誰手上、過去的借用紀錄。
     * 大量借用品項是整池借出、不指定單件，所以池內器材沒有單件紀錄。
     */
    public function show(Property $property): Response
    {
        $property->load(['subcategory.category:id,code,name', 'location:id,name', 'pool:id,name']);

        $history = BorrowRequestOverview::query()
            ->whereHas('items', fn (Builder $q) => $q->where('property_id', $property->id))
            ->with([
                'items' => fn ($q) => $q->where('property_id', $property->id)->with('returner:id,name'),
                'lender:id,name',
                'returner:id,name',
            ])
            ->orderByDesc('borrow_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (BorrowRequestOverview $b) => $this->historyRow($b));

        $current = BorrowRequestOverview::query()
            ->whereHas('items', fn (Builder $q) => $q->where('property_id', $property->id)->active())
            ->with([
                'items' => fn ($q) => $q->where('property_id', $property->id),
                'lender:id,name',
            ])
            ->first();

        return Inertia::render('Admin/Properties/Show', [
            'property' => [
                ...$property->toArray(),
                'image_url' => PropertyImage::url($property->image_path),
            ],
            'current' => $current ? $this->historyRow($current) : null,
            'stats' => $this->borrowStats($property),
            'history' => $history,
        ]);
    }

    /** @return array<string, mixed> */
    private function historyRow(BorrowRequestOverview $b): array
    {
        /** @var BorrowRequestItem|null $item */
        $item = $b->items->first();
        $itemReturnedAt = $item?->returned_at;

        return [
            'id' => $b->id,
            'status' => $b->status,
            'item_status' => $item?->status,
            'borrower_department' => $b->borrower_department,
            'borrower_name' => $b->borrower_name,
            'phone' => $b->phone,
            'borrow_date' => $b->borrow_date->toDateString(),
            'due_date' => $b->due_date->toDateString(),
            // 以台北時間的日期給前端：舊資料只有日期（00:00），轉成 UTC 會變成前一天
            'lent_on' => $b->lent_at?->toDateString(),
            'returned_on' => $itemReturnedAt?->toDateString(),
            'lender' => $b->lender->name ?? $b->legacy_lender_name,
            'returner' => $item?->returner->name ?? $b->returner->name ?? $b->legacy_returner_name,
            'extended' => $b->extended_at !== null,
            // 這一件是否晚還（或至今未還且已過歸還日）
            'late' => $item !== null && match ($item->status) {
                BorrowItemStatus::Returned => $itemReturnedAt !== null && $itemReturnedAt->startOfDay()->greaterThan($b->due_date),
                BorrowItemStatus::Lent => $b->due_date->lessThan(today()),
                default => false,
            },
        ];
    }

    /** @return array{times: int, days: int, late: int, last_lent_on: ?string} */
    private function borrowStats(Property $property): array
    {
        $row = DB::table('borrow_request_items as i')
            ->join('borrow_requests as r', 'r.id', '=', 'i.borrow_request_id')
            ->where('i.property_id', $property->id)
            ->whereIn('i.status', [BorrowItemStatus::Lent->value, BorrowItemStatus::Returned->value])
            ->selectRaw('count(*) AS times')
            ->selectRaw('max(r.lent_at) AS last_lent_at')
            // 借出到歸還（未還算到今天）的天數，同一天借還算 1 天
            ->selectRaw('coalesce(sum(coalesce(i.returned_at::date, current_date) - coalesce(r.lent_at::date, r.borrow_date) + 1), 0) AS days')
            ->selectRaw("count(*) FILTER (WHERE coalesce(i.returned_at::date, CASE WHEN i.status = 'lent' THEN current_date END) > r.due_date) AS late")
            ->first();

        return [
            'times' => (int) ($row->times ?? 0),
            'days' => (int) ($row->days ?? 0),
            'late' => (int) ($row->late ?? 0),
            'last_lent_on' => $row?->last_lent_at !== null ? substr((string) $row->last_lent_at, 0, 10) : null,
        ];
    }

    public function edit(Property $property): Response
    {
        return Inertia::render('Admin/Properties/Form', [
            'property' => [
                ...$property->toArray(),
                'image_url' => PropertyImage::url($property->image_path),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(PropertyRequest $request, Property $property): RedirectResponse
    {
        $property->fill($request->safe()->except(['image', 'remove_image']));

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $this->deleteImage($property->image_path);
            $property->image_path = $this->storeImage($request->file('image'));
        }

        $property->save();

        Toast::success("已更新 {$property->ssid}。");

        return to_route('admin.properties.index');
    }

    public function destroy(Property $property): RedirectResponse
    {
        if ($property->borrowItems()->exists()) {
            throw ValidationException::withMessages([
                'property' => '這件器材已有借用紀錄，不能刪除；如果不再出借，請改為「不開放借用」。',
            ]);
        }

        $property->delete();
        $this->deleteImage($property->image_path);

        Toast::success("已刪除 {$property->ssid}。");

        return to_route('admin.properties.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'locations' => Location::orderBy('sort_order')->get(['id', 'code', 'name', 'ssid_prefix']),
            'categories' => PropertyCategory::with('subcategories:id,property_category_id,code,name')->orderBy('code')->get(['id', 'code', 'name']),
            'pools' => PropertyPool::with('location:id,name')->orderBy('name')->get(['id', 'name', 'location_id']),
            'authLevels' => AuthLevel::options(),
        ];
    }

    private function storeImage(?UploadedFile $file): ?string
    {
        return $file?->storeAs('properties', Str::uuid().'.'.$file->extension(), 'public') ?: null;
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
