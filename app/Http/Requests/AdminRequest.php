<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [
            // Basic User Information
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => $this->method() == 'POST' ? 'required|email|unique:admins,email,'.$this->id : 'required',
            'photo' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            'phone' => 'required|string|max:15',
            'role_id' => 'required|exists:roles,id',
            'password' => $this->method() == 'POST' ? 'required|string|min:6' : 'nullable|string|min:6',
            
            // Admin Details
            'staff_id' => $this->method() == 'POST' ? 'required|string|max:255|unique:admin_details,staff_id,'.$this->id.'' : 'required',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'gender' => 'required|in:Male,Female,Other',
            'marital_status' => 'required|in:Single,Married,Divorced,Widowed',
            'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'date_of_birth' => 'nullable|date',
            'date_of_joining' => 'required|date',
            'emergency_contact' => 'nullable|string|max:20',
            'designation_id' => 'required',
            'department_id' => 'required',
            'specialist_id' => 'nullable',
            'current_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'pan_number' => 'nullable|string|max:255',
            'national_id_number' => 'nullable|string|max:255',
            'local_id_number' => 'nullable|string|max:255',
            'qualification' => 'nullable|string',
            'work_experience' => 'nullable|string',
            'specialization' => 'nullable|string',
            'note' => 'nullable|string',
            'charge' => 'nullable|numeric',
            
            // Payroll
            'epf_no' => 'nullable|string|max:255',
            'basic_salary' => 'nullable|numeric',
            'contract_type' => 'nullable|in:Permanent,Probation',
            'work_shift' => 'nullable|string|max:255',
            'work_location' => 'nullable|string|max:255',
            
            // Leave
            'number_of_leaves' => 'nullable|integer',
            
            // Bank Details
            'bank_account_title' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_branch_name' => 'nullable|string|max:255',
            'ifsc_code' => 'nullable|string|max:255',
            
            // Social Media
            'facebook_url' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'twitter_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            
            // Documents
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:25048',
            'joining_letter' => 'nullable|file|mimes:pdf,doc,docx|max:25048',
            'resignation_letter' => 'nullable|file|mimes:pdf,doc,docx|max:25048',
            'other_documents' => 'nullable|file|mimes:pdf,doc,docx|max:25048',
        ];

        // Add conditional validation for charge when role is Doctor
        if ($this->input('role_id') == 2 ) {
            $rules['doctor_charge'] = 'required|numeric|min:0';
            $rules['specialist_id'] = 'required';
        }

        return $rules;
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, mixed>
     */
    public function messages()
    {
        return [
            // Basic User Information
            'first_name.required' => __('The first name field is required.'),
            'last_name.required' => __('The last name field is required.'),
            'email.required' => __('The email field is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email address is already taken.'),
            'photo.file' => __('The photo must be a file.'),
            'photo.mimes' => __('The photo must be a file of type: png, jpg, jpeg.'),
            'photo.max' => __('The photo may not be greater than :max kilobytes.'),
            'phone.max' => __('The phone number may not be greater than :max characters.'),
            'role_id.required' => __('The role field is required.'),
            'role_id.exists' => __('Selected role does not exist.'),
            'password.required' => __('The password field is required.'),
            'password.min' => __('The password must be at least :min characters.'),
            
            // Admin Details
            'staff_id.unique' => __('This staff ID is already taken.'),
            'gender.in' => __('Please select a valid gender.'),
            'marital_status.in' => __('Please select a valid marital status.'),
            'blood_group.in' => __('Please select a valid blood group.'),
            'date_of_birth.date' => __('Please enter a valid date of birth.'),
            'date_of_joining.date' => __('Please enter a valid date of joining.'),
            'designation_id.exists' => __('Selected designation does not exist.'),
            'department_id.exists' => __('Selected department does not exist.'),
            'specialist_id.exists' => __('Selected specialist does not exist.'),
            'charge.required' => __('The charge field is required for doctors.'),
            'charge.numeric' => __('The charge must be a number.'),
            'charge.min' => __('The charge must be at least 0.'),
            
            // Payroll
            'basic_salary.numeric' => __('Basic salary must be a number.'),
            'contract_type.in' => __('Please select a valid contract type.'),
            'number_of_leaves.integer' => __('Number of leaves must be an integer.'),
            
            // Social Media
            'facebook_url.url' => __('Please enter a valid Facebook URL.'),
            'linkedin_url.url' => __('Please enter a valid LinkedIn URL.'),
            'twitter_url.url' => __('Please enter a valid Twitter URL.'),
            'instagram_url.url' => __('Please enter a valid Instagram URL.'),
            
            // Documents
            'resume.mimes' => __('The resume must be a file of type: pdf, doc, docx.'),
            'joining_letter.mimes' => __('The joining letter must be a file of type: pdf, doc, docx.'),
            'resignation_letter.mimes' => __('The resignation letter must be a file of type: pdf, doc, docx.'),
            'other_documents.mimes' => __('Other documents must be a file of type: pdf, doc, docx.'),
            
            // General
            '*.max' => __('The :attribute may not be greater than :max characters.'),
        ];
    }
}