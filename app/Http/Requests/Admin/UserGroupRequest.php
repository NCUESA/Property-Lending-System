<?php

namespace App\Http\Requests\Admin;

use App\Models\UserGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 路由已套用 can:admin
    }

    /**
     * 新增時只需要名稱與說明；編輯時同時送出成員、子群組與授權（整批同步）。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $group = $group instanceof UserGroup ? $group : null;

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('user_groups', 'name')->ignore($group)],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'subgroup_ids' => ['sometimes', 'array'],
            'subgroup_ids.*' => ['integer', 'distinct', 'exists:user_groups,id', Rule::notIn(array_filter([$group?->id]))],
            'grants' => ['sometimes', 'array'],
            'grants.property_ids' => ['array'],
            'grants.property_ids.*' => ['integer', 'distinct', 'exists:properties,id'],
            'grants.category_ids' => ['array'],
            'grants.category_ids.*' => ['integer', 'distinct', 'exists:property_categories,id'],
            'grants.subcategory_ids' => ['array'],
            'grants.subcategory_ids.*' => ['integer', 'distinct', 'exists:property_subcategories,id'],
            'grants.pool_ids' => ['array'],
            'grants.pool_ids.*' => ['integer', 'distinct', 'exists:property_pools,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '群組名稱',
            'description' => '說明',
            'member_ids' => '成員',
            'subgroup_ids' => '子群組',
            'subgroup_ids.*' => '子群組',
        ];
    }

    public function messages(): array
    {
        return ['subgroup_ids.*.not_in' => '群組不能包含自己。'];
    }
}
