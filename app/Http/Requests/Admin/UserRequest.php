<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 路由已套用 can:admin
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'student_id' => ['nullable', 'string', 'max:20', Rule::unique('users', 'student_id')->ignore($user), 'required_without:email'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user), 'required_without:student_id'],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                $self = $this->user();

                // 避免管理員把自己降級或停用，導致沒有人能管理系統
                if ($target instanceof User && $self->is($target)) {
                    if ($this->input('role') !== Role::Admin->value) {
                        $validator->errors()->add('role', '不能變更自己的角色。');
                    }
                    if (! $this->boolean('is_active')) {
                        $validator->errors()->add('is_active', '不能停用自己的帳號。');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['student_id' => '學號', 'name' => '姓名', 'email' => '電子郵件', 'role' => '角色', 'is_active' => '啟用狀態'];
    }
}
