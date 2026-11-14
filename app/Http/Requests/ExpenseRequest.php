<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $departmentName = trim((string) ($this->input('department_name') ?? ''));
        if ($this->missing('department_id') && $departmentName !== '') {
            $department = \App\Models\Department::whereRaw('LOWER(name) = ?', [strtolower($departmentName)])
                ->first();

            if ($department) {
                $this->merge(['department_id' => $department->id]);
            }
        }

        $expenseHeadName = trim((string) ($this->input('expense_head_name') ?? ''));
        if ($this->missing('expense_header_id') && $expenseHeadName !== '') {
            $expenseHead = \App\Models\ExpenseHead::whereRaw('LOWER(name) = ?', [strtolower($expenseHeadName)])
                ->first();

            if ($expenseHead) {
                $this->merge(['expense_header_id' => $expenseHead->id]);
            }
        }
    }

    public function rules()
    {
        $expenseId = $this->route('id') ?? $this->route('expense');

        switch ($this->method()) {
            case 'POST':
                return [
                    'expense_header_id' => ['nullable', 'integer', Rule::exists('expenseheads', 'id')],
                    'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
                    'bill_number' => 'nullable|string|unique:expenses,bill_number|max:255',
                    'name' => 'required|string|max:255',
                    'document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                    'description' => 'nullable|string',
                    'amount' => 'required|numeric|min:0|max:999999999999.99',
                    'date' => 'required|date|before_or_equal:today',
                ];

            case 'PUT':
            case 'PATCH':
                return [
                    'expense_header_id' => ['nullable', 'integer', Rule::exists('expenseheads', 'id')],
                    'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
                    // 'invoice_number' => [
                    //     'nullable',
                    //     'string',
                    //     'max:255',
                    //     Rule::unique('expenses', 'invoice_number')->ignore($expenseId)
                    // ],
                    'bill_number' => [
                        'nullable',
                        'string',
                        'max:255',
                        Rule::unique('expenses', 'bill_number')->ignore($expenseId)
                    ],
                    'case_id' => [
                        'nullable',
                        'string',
                        'max:255',
                        Rule::unique('expenses', 'case_id')->ignore($expenseId)
                    ],
                    'name' => 'required|string|max:255',
                    'document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                    'description' => 'nullable|string',
                    'amount' => 'required|numeric|min:0|max:999999999999.99',
                    'date' => 'required|date|before_or_equal:today',
                ];

            default:
                return [];
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $departmentId = $this->input('department_id');
            $departmentName = trim((string) ($this->input('department_name') ?? ''));
            if (empty($departmentId) && $departmentName === '') {
                $validator->errors()->add('department_id', 'Please select a department.');
            }

            $expenseHeadId = $this->input('expense_header_id');
            $expenseHeadName = trim((string) ($this->input('expense_head_name') ?? ''));
            if (empty($expenseHeadId) && $expenseHeadName === '') {
                $validator->errors()->add('expense_header_id', 'Please select an expense head.');
            }
        });
    }

    public function messages()
    {
        return [
            'expense_header_id.required' => 'Please select an expense head.',
            'expense_header_id.exists' => 'The selected expense head is invalid.',
            'bill_number.unique' => 'This bill number has already been taken.',
            'case_id.unique' => 'This case ID has already been taken.',
            'name.required' => 'The expense name field is required.',
            'name.string' => 'The expense name must be a string.',
            'name.max' => 'The expense name may not be greater than 255 characters.',
            'document.file' => 'The document must be a file.',
            'document.mimes' => 'The document must be a file of type: pdf, doc, docx, jpg, jpeg, png.',
            'document.max' => 'The document may not be greater than 10MB.',
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.min' => 'The amount must be at least 0.',
            'amount.max' => 'The amount is too large.',
            'date.required' => 'The date field is required.',
            'date.date' => 'The date must be a valid date.',
            'date.before_or_equal' => 'The date must be today or before.',
        ];
    }

    public function attributes()
    {
        return [
            'expense_header_id' => 'expense head',
            'invoice_number' => 'invoice number',
            'bill_number' => 'bill number',
            'case_id' => 'case ID',
            'name' => 'expense name',
            'document' => 'document',
            'description' => 'description',
            'amount' => 'amount',
            'date' => 'date',
        ];
    }
}