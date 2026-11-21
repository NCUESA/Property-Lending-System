<?php

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/*
 * 舊系統的寫入端點（/add-user、/ip/add、/property/info/update…）沒有掛權限，
 * 知道網址就能改資料。這裡掃描所有路由，確保不會再發生。
 */

// 允許訪客使用的寫入路由
const PUBLIC_WRITE_ROUTES = [
    'borrow.store',
];

it('所有寫入路由都需要登入（白名單除外）', function () {
    $unprotected = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $r) => array_diff($r->methods(), ['GET', 'HEAD']) !== [])
        ->reject(fn (Route $r) => in_array($r->getName(), PUBLIC_WRITE_ROUTES, true))
        ->reject(fn (Route $r) => collect($r->gatherMiddleware())->contains(fn ($m) => $m === 'auth' || str_starts_with($m, 'auth:')))
        ->map(fn (Route $r) => implode('|', $r->methods()).' '.$r->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('後台路由都有角色權限', function () {
    $missing = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $r) => str_starts_with($r->uri(), 'admin/'))
        ->reject(fn (Route $r) => array_intersect(['can:staff', 'can:admin'], $r->gatherMiddleware()) !== [])
        ->map(fn (Route $r) => $r->uri())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('訪客進後台會被導向登入頁', function () {
    $this->get(route('admin.borrows.index'))->assertRedirect(route('login'));
    $this->post(route('admin.users.store'))->assertRedirect(route('login'));
});

it('依角色限制後台', function (string $role, string $route, int $status, mixed $params = []) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route($route, $params))->assertStatus($status);
})->with([
    ['borrower', 'admin.borrows.index', 403],
    ['staff', 'admin.borrows.index', 200],
    ['staff', 'admin.properties.index', 403],
    ['staff', 'admin.users.index', 403],
    ['admin', 'admin.properties.index', 200],
    ['admin', 'admin.users.index', 200],
    ['admin', 'admin.groups.index', 200],
    ['admin', 'admin.categories.index', 200],
    ['admin', 'admin.pools.index', 200],
    ['admin', 'admin.periods.index', 200],
    ['admin', 'admin.settings.section.edit', 200, 'borrow'],
    ['admin', 'admin.settings.section.edit', 200, 'notices'],
    // admin/settings 本身只是導向第一個分頁，但仍受 can:admin 保護
    ['admin', 'admin.settings.edit', 302],
    ['staff', 'admin.settings.edit', 403],
    ['staff', 'admin.settings.section.edit', 403, 'borrow'],
]);

it('停用的帳號即使是管理員也進不了後台', function () {
    $this->actingAs(User::factory()->admin()->inactive()->create())
        ->get(route('admin.borrows.index'))
        ->assertForbidden();
});

it('staff 不能直接打管理員的寫入端點', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.users.store'), ['name' => 'x', 'student_id' => 'X1', 'role' => 'admin', 'is_active' => true])
        ->assertForbidden();

    expect(User::where('student_id', 'X1')->exists())->toBeFalse();
});
