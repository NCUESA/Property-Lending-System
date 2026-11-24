<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyRequest;
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
            'pool_id' => ['nullable', 'integer'],
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
            ->when($filters['pool_id'] ?? null, fn (Builder $q, int $id) => $q->where('property_pool_id', $id))
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
