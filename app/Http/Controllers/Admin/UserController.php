<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 人員管理。帳號多半由 SSO 第一次登入時自動建立（借用人），
 * 管理員在這裡調整角色、停用帳號，或預先建立帳號（以 email／學號在 SSO 登入時綁定）。
 * 不提供刪除：帳號可能是借用單的經手人，改用停用。
 */
class UserController extends Controller
{
    /** 每頁筆數的可選值，第一個是預設值，與器材列表相同。 */
    public const PER_PAGE_OPTIONS = PropertyController::PER_PAGE_OPTIONS;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $users = User::query()
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('name', 'ilike', "%{$term}%")
                ->orWhere('student_id', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")))
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->where('role', $role))
            ->with('groups:id,name')
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'staff' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0])
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'filters' => $filters,
            'users' => $users,
            'roles' => collect(Role::cases())->map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()]),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        Toast::success('已新增帳號。');

        return back();
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        Toast::success('已更新帳號。');

        return back();
    }
}
