<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Validasi tambah & ubah user oleh IT Admin.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'is_active' => $this->boolean('is_active'),
            'unit_ids' => array_values(array_filter((array) $this->input('unit_ids', []))),
            'primary_unit_id' => $this->filled('primary_unit_id') ? (int) $this->input('primary_unit_id') : null,
        ]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = $userId === null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['boolean'],
            'password' => [$isCreate ? 'required' : 'prohibited', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'unit_ids' => ['array'],
            'unit_ids.*' => ['integer', 'distinct', Rule::exists('units', 'id')],
            'primary_unit_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $role = Role::find($this->input('role_id'));
                $unitIds = array_map('intval', $this->input('unit_ids', []));

                if ($role?->code === RoleCode::USER && $unitIds === []) {
                    $validator->errors()->add('unit_ids', 'User dengan role User wajib memiliki minimal satu unit.');
                }

                $primary = $this->input('primary_unit_id');
                if ($primary !== null && ! in_array((int) $primary, $unitIds, true)) {
                    $validator->errors()->add('primary_unit_id', 'Unit utama harus termasuk unit yang dipilih.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama', 'username' => 'username', 'email' => 'email', 'phone' => 'nomor HP',
            'role_id' => 'role', 'password' => 'password', 'unit_ids' => 'unit', 'unit_ids.*' => 'unit',
            'primary_unit_id' => 'unit utama',
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
            'password.prohibited' => 'Gunakan fitur Reset Password untuk mengganti password user.',
        ];
    }
}
