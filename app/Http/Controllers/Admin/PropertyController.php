<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BorrowItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyRequest;
use App\Models\BorrowItemUnit;
use App\Models\BorrowRequestItem;
use App\Models\BorrowRequestOverview;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Support\AuthLevel;
use App\Support\PropertyCsv;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyController extends Controller
{
    /** 每頁筆數的可選值，第一個是預設值。前端的下拉選單也用這份清單。 */
    public const PER_PAGE_OPTIONS = [10, 30, 50, 100];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            ...self::FILTER_RULES,
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $properties = self::filtered($filters)
            ->with([
                'subcategory.category:id,code,name', 'location:id,name', 'pool:id,name',
                'activeBorrowItem:id,property_id,status,borrow_request_id',
                'activeUnit:id,borrow_request_item_id,property_id',
                'activeUnit.item:id,borrow_request_id',
            ])
            ->orderBy('ssid')
            ->paginate($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0])
            ->withQueryString()
            ->through(fn (Property $p) => $this->withActiveUnit($p)->setAttribute('image_url', PropertyImage::url($p->image_path)));

        return Inertia::render('Admin/Properties/Index', [
            'filters' => $filters,
            'properties' => $properties,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            ...$this->formOptions(),
        ]);
    }

    /** 匯出目前篩選條件下的器材清冊（CSV，可改完再匯入）。 */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate(self::FILTER_RULES);

        return PropertyCsv::export(self::filtered($filters), '器材清冊-'.now()->format('Ymd').'.csv');
    }

    /**
     * 以大量借用品項借出的器材沒有自己的明細，列表上用它所在的那一行當作「借出中」顯示。
     */
    private function withActiveUnit(Property $p): Property
    {
        if ($p->activeBorrowItem === null && $p->activeUnit !== null) {
            $p->setRelation('activeBorrowItem', (new BorrowRequestItem)->forceFill([
                'id' => $p->activeUnit->borrow_request_item_id,
                'property_id' => $p->id,
                'status' => BorrowItemStatus::Lent,
                'borrow_request_id' => $p->activeUnit->item->borrow_request_id,
            ]));
        }

        return $p->unsetRelation('activeUnit');
    }

    /** 列表與匯出共用的篩選條件 */
    private const FILTER_RULES = [
        'q' => ['nullable', 'string', 'max:50'],
        'location_id' => ['nullable', 'integer'],
        'category_id' => ['nullable', 'integer'],
        // 品項池 id，或 none = 只看不屬於任何品項池的器材
        'pool_id' => ['nullable', 'regex:/^(none|\d+)$/'],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Property>
     */
    private static function filtered(array $filters): Builder
    {
        return Property::query()
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('ssid', 'ilike', "%{$term}%")
                ->orWhere('name', 'ilike', "%{$term}%")
                ->orWhere('second_name', 'ilike', "%{$term}%")
                ->orWhere('custodian', 'ilike', "%{$term}%")))
            ->when($filters['location_id'] ?? null, fn (Builder $q, int $id) => $q->where('location_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('subcategory', fn (Builder $s) => $s->where('property_category_id', $id)))
            ->when($filters['pool_id'] ?? null, fn (Builder $q, string $pool) => $pool === 'none'
                ? $q->whereNull('property_pool_id')
                : $q->where('property_pool_id', (int) $pool));
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
        $id = $property->id;

        // 單件借出（明細直接指定這件），或以大量借用品項借出時掃到這件
        $involves = fn (Builder $q) => $q->where('property_id', $id)
            ->orWhereHas('units', fn (Builder $u) => $u->where('property_id', $id));
        $relations = [
            'items' => fn ($q) => $q->where($involves)->with([
                'returner:id,name',
                'units' => fn ($u) => $u->where('property_id', $id)->with('returner:id,name'),
            ]),
            'lender:id,name',
            'returner:id,name',
        ];

        $history = BorrowRequestOverview::query()
            ->whereHas('items', $involves)
            ->with($relations)
            ->orderByDesc('borrow_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (BorrowRequestOverview $b) => $this->historyRow($b));

        $current = BorrowRequestOverview::query()
            ->whereHas('items', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where(fn (Builder $serial) => $serial->where('property_id', $id)->active())
                ->orWhereHas('units', fn (Builder $u) => $u->where('property_id', $id)->whereNull('returned_at'))))
            ->with($relations)
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
        /** @var BorrowItemUnit|null $unit 以大量借用品項借出時的這一件 */
        $unit = $item?->units->first();

        // 大量借用品項以單件紀錄為準：這一件可能比同一行的其他件早還
        if ($unit !== null) {
            $status = $unit->returned_at !== null ? BorrowItemStatus::Returned : BorrowItemStatus::Lent;
            $returnedAt = $unit->returned_at;
            $returner = $unit->returner?->name;
        } else {
            $status = $item?->status;
            $returnedAt = $item?->returned_at;
            $returner = $item?->returner?->name;
        }

        return [
            'id' => $b->id,
            'status' => $b->status,
            'item_status' => $status,
            'via_pool' => $unit !== null ? $item->pool->name ?? '大量借用' : null,
            'borrower_department' => $b->borrower_department,
            'borrower_name' => $b->borrower_name,
            'phone' => $b->phone,
            'borrow_date' => $b->borrow_date->toDateString(),
            'due_date' => $b->due_date->toDateString(),
            // 以台北時間的日期給前端：舊資料只有日期（00:00），轉成 UTC 會變成前一天
            'lent_on' => $b->lent_at?->toDateString(),
            'returned_on' => $returnedAt?->toDateString(),
            'lender' => $b->lender->name ?? $b->legacy_lender_name,
            'returner' => $returner ?? $b->returner->name ?? $b->legacy_returner_name,
            'extended' => $b->extended_at !== null,
            // 這一件是否晚還（或至今未還且已過歸還日）
            'late' => match ($status) {
                BorrowItemStatus::Returned => $returnedAt !== null && $returnedAt->startOfDay()->greaterThan($b->due_date),
                BorrowItemStatus::Lent => $b->due_date->lessThan(today()),
                default => false,
            },
        ];
    }

    /** @return array{times: int, days: int, late: int, last_lent_on: ?string} */
    private function borrowStats(Property $property): array
    {
        // 單件借出的明細，與大量借用品項掃到這件的紀錄，合在一起統計
        $serial = DB::table('borrow_request_items as i')
            ->join('borrow_requests as r', 'r.id', '=', 'i.borrow_request_id')
            ->where('i.property_id', $property->id)
            ->whereIn('i.status', [BorrowItemStatus::Lent->value, BorrowItemStatus::Returned->value])
            ->selectRaw("i.returned_at, i.status = 'lent' AS open, r.lent_at, r.borrow_date, r.due_date");

        $units = DB::table('borrow_item_units as u')
            ->join('borrow_request_items as i', 'i.id', '=', 'u.borrow_request_item_id')
            ->join('borrow_requests as r', 'r.id', '=', 'i.borrow_request_id')
            ->where('u.property_id', $property->id)
            ->selectRaw('u.returned_at, u.returned_at IS NULL AS open, r.lent_at, r.borrow_date, r.due_date');

        $row = DB::query()->fromSub($serial->unionAll($units), 'x')
            ->selectRaw('count(*) AS times')
            ->selectRaw('max(lent_at) AS last_lent_at')
            // 借出到歸還（未還算到今天）的天數，同一天借還算 1 天
            ->selectRaw('coalesce(sum(coalesce(returned_at::date, current_date) - coalesce(lent_at::date, borrow_date) + 1), 0) AS days')
            ->selectRaw('count(*) FILTER (WHERE coalesce(returned_at::date, CASE WHEN open THEN current_date END) > due_date) AS late')
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
