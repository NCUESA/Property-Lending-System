<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserGroupRequest;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\DatabaseError;
use App\Support\Toast;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserGroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Groups/Index', [
            'groups' => UserGroup::query()
                ->withCount(['members', 'subgroups', 'grants'])
                ->with('parentGroups:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(UserGroupRequest $request): RedirectResponse
    {
        $group = UserGroup::create($request->safe()->only(['name', 'description']));

        Toast::success('已建立群組，請設定成員與授權。');

        return to_route('admin.groups.edit', $group);
    }

    public function edit(UserGroup $group): Response
    {
        $group->load(['members:id,name,student_id', 'subgroups:id,name', 'grants']);

        return Inertia::render('Admin/Groups/Edit', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'member_ids' => $group->members->pluck('id'),
                'subgroup_ids' => $group->subgroups->pluck('id'),
                'grants' => [
                    'property_ids' => $group->grants->pluck('property_id')->filter()->values(),
                    'category_ids' => $group->grants->pluck('property_category_id')->filter()->values(),
                    'subcategory_ids' => $group->grants->pluck('property_subcategory_id')->filter()->values(),
                    'pool_ids' => $group->grants->pluck('property_pool_id')->filter()->values(),
                ],
            ],
            'options' => [
                'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'student_id']),
                'groups' => UserGroup::whereKeyNot($group->id)->orderBy('name')->get(['id', 'name']),
                'categories' => PropertyCategory::with('subcategories:id,property_category_id,code,name')->orderBy('code')->get(['id', 'code', 'name']),
                // 只有 auth_level > 0 的器材需要授權
                'properties' => Property::where('auth_level', '>', 0)->serial()->orderBy('ssid')->get(['id', 'ssid', 'name', 'auth_level']),
                'pools' => PropertyPool::with('location:id,name')->orderBy('name')->get(['id', 'name', 'location_id', 'auth_level']),
            ],
        ]);
    }

    public function update(UserGroupRequest $request, UserGroup $group): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($group, $data) {
                $group->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);

                if (array_key_exists('member_ids', $data)) {
                    $group->members()->sync($data['member_ids']);
                }
                if (array_key_exists('subgroup_ids', $data)) {
                    $group->subgroups()->sync($data['subgroup_ids']);
                }
                if (array_key_exists('grants', $data)) {
                    $this->syncGrants($group, $data['grants']);
                }
            });
        } catch (QueryException $e) {
            if (DatabaseError::is($e, DatabaseError::CHECK_VIOLATION, '循環')) {
                throw ValidationException::withMessages(['subgroup_ids' => '選取的子群組已經包含這個群組，會形成循環。']);
            }

            throw $e;
        }

        Toast::success('已更新群組。');

        return back();
    }

    public function destroy(UserGroup $group): RedirectResponse
    {
        $group->delete();

        Toast::success("已刪除群組「{$group->name}」。");

        return to_route('admin.groups.index');
    }

    /**
     * @param  array{property_ids?: list<int>, category_ids?: list<int>, subcategory_ids?: list<int>, pool_ids?: list<int>}  $grants
     */
    private function syncGrants(UserGroup $group, array $grants): void
    {
        $group->grants()->delete();

        $rows = collect([
            'property_id' => $grants['property_ids'] ?? [],
            'property_category_id' => $grants['category_ids'] ?? [],
            'property_subcategory_id' => $grants['subcategory_ids'] ?? [],
            'property_pool_id' => $grants['pool_ids'] ?? [],
        ])->flatMap(fn (array $ids, string $column) => array_map(fn (int $id) => [$column => $id], $ids));

        $group->grants()->createMany($rows->all());
    }
}
