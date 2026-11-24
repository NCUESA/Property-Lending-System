<?php

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(LocationSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->jinde = Location::where('code', 'jinde')->sole(); // 編號代碼 J
    $category = PropertyCategory::create(['code' => '011', 'name' => '放置用具']);
    $this->chair = PropertySubcategory::create(['property_category_id' => $category->id, 'code' => '0', 'name' => '折疊椅']);

    $this->existing = Property::factory()->create([
        'ssid' => 'J0110001',
        'name' => '折疊椅',
        'property_subcategory_id' => $this->chair->id,
        'location_id' => $this->jinde->id,
        'price' => 300,
    ]);
});

function csvFile(string $content, string $name = 'import.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

/** 上傳並回傳預覽頁 */
function previewCsv(string $content): TestResponse
{
    $redirect = test()->post(route('admin.properties.import.preview'), ['file' => csvFile($content)]);
    $redirect->assertSessionHasNoErrors()->assertRedirect();

    return test()->get($redirect->headers->get('Location'));
}

function tokenOf(TestResponse $page): string
{
    return $page->viewData('page')['props']['token'];
}

test('匯出 CSV 帶 BOM 與中文欄位，並套用篩選條件', function () {
    Property::factory()->create(['ssid' => 'J2000001', 'name' => '麥克風']);

    $response = $this->get(route('admin.properties.export', ['q' => '折疊']));
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF財產編號,名稱,")
        ->toContain('J0110001,折疊椅,,011,放置用具,0,折疊椅,')
        ->toContain(',在庫')
        ->not->toContain('J2000001');
});

test('匯出的檔案改完可以直接匯入更新', function () {
    $csv = $this->get(route('admin.properties.export'))->streamedContent();
    $csv = str_replace('J0110001,折疊椅,', 'J0110001,藍色折疊椅,', $csv);

    $page = previewCsv($csv)->assertInertia(fn (Assert $p) => $p
        ->component('Admin/Properties/Import')
        ->where('rows.0.action', 'update')
        ->where('rows.0.changes', ['名稱']));

    $this->post(route('admin.properties.import.store'), ['token' => tokenOf($page)])
        ->assertRedirect(route('admin.properties.index'));

    expect($this->existing->fresh())->name->toBe('藍色折疊椅')->price->toBe(300);
});

test('新器材的財產編號留空時自動接續編號', function () {
    $pool = PropertyPool::factory()->create(['name' => '折疊椅池', 'location_id' => $this->jinde->id]);

    $page = previewCsv(<<<'CSV'
        財產編號,名稱,類別碼,子類別碼,存放地點,大量借用品項,價格,校產
        ,折疊椅,011,0,進德,折疊椅池,"1,200",是
        ,折疊椅,11,0,jinde,折疊椅池,,否
        CSV)->assertInertia(fn (Assert $p) => $p
        ->where('rows.0.action', 'create')
        ->where('rows.0.ssid', 'J0110002')
        ->where('rows.1.ssid', 'J0110003'));

    $this->post(route('admin.properties.import.store'), ['token' => tokenOf($page)]);

    $new = Property::where('ssid', 'J0110002')->sole();
    expect($new)
        ->property_pool_id->toBe($pool->id)
        ->price->toBe(1200)
        ->is_school_owned->toBeTrue()
        ->is_lendable->toBeTrue()
        ->sequence_no->toBe(2)
        ->and(Property::where('ssid', 'J0110003')->exists())->toBeTrue();
});

test('沒有的欄位保留原值，空白儲存格清空', function () {
    $this->existing->update(['custodian' => '王小明', 'remark' => '舊備註']);

    $page = previewCsv("財產編號,備註\nJ0110001,\n");

    $this->post(route('admin.properties.import.store'), ['token' => tokenOf($page)]);

    expect($this->existing->fresh())->remark->toBeNull()->custodian->toBe('王小明');
});

test('有錯誤的列會列出原因，整批不匯入', function () {
    $page = previewCsv(<<<'CSV'
        財產編號,名稱,類別碼,子類別碼,存放地點,開放借用
        J0110001,折疊椅,011,0,進德,也許
        ,麥克風,200,0,進德,是
        ,喇叭,011,0,火星,是
        J0110009,桌子,011,0,進德,是
        J0110009,桌子,011,0,進德,是
        ABC,桌子,011,0,進德,是
        ,,011,0,進德,是
        CSV)->assertInertia(fn (Assert $p) => $p
        ->where('rows.0.errors', ['「開放借用」請填 是 或 否'])
        ->where('rows.1.errors', ['找不到類別碼＋子類別碼 200-0，請先到編碼規則新增'])
        ->where('rows.2.errors', ['找不到存放地點「火星」'])
        ->where('rows.3.action', 'create')
        ->where('rows.4.errors', ['財產編號 J0110009 和第 5 列重複'])
        ->where('rows.5.action', 'error')
        ->where('rows.6.errors', ['新器材必須填「名稱」']));

    $this->post(route('admin.properties.import.store'), ['token' => tokenOf($page)])
        ->assertSessionHasErrors('file');

    expect(Property::count())->toBe(1);
});

test('看得懂 Excel 存的 Big5 CSV', function () {
    $big5 = mb_convert_encoding("財產編號,名稱\nJ0110001,摺疊椅\n", 'CP950', 'UTF-8');

    previewCsv($big5)->assertInertia(fn (Assert $p) => $p
        ->where('rows.0.name', '摺疊椅')
        ->where('rows.0.changes', ['名稱']));
});

test('欄位名稱看不懂時拒絕', function () {
    $this->post(route('admin.properties.import.preview'), ['file' => csvFile("編號,名稱\nJ0110001,椅子\n")])
        ->assertSessionHasErrors(['file' => '看不懂這些欄位：編號。請用匯出的檔案當範本。']);
});

test('只有上傳的人能確認匯入', function () {
    $page = previewCsv("財產編號,名稱\nJ0110001,椅子\n");

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.properties.import.store'), ['token' => tokenOf($page)])
        ->assertSessionHasErrors('file');

    expect($this->existing->fresh()->name)->toBe('折疊椅');
});
