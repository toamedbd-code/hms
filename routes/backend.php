<?php

use App\Http\Controllers\Backend\AdminController;
use App\Http\Controllers\Backend\DashboardController;
use App\Http\Controllers\Backend\PermissionController;
use App\Http\Controllers\Backend\RoleController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\PatientController;
use App\Http\Controllers\Backend\TpaController;
use App\Http\Controllers\Backend\BillingController;
use App\Http\Controllers\Backend\AppoinmentController;
use App\Http\Controllers\Backend\OpdPatientController;
use App\Http\Controllers\Backend\IpdPatientController;
use App\Http\Controllers\Backend\PharmacyController;
use App\Http\Controllers\Backend\PathologyController;
use App\Http\Controllers\Backend\RadiologyController;
use App\Http\Controllers\Backend\BloodBankController;
use App\Http\Controllers\Backend\AmbulanceController;
use App\Http\Controllers\Backend\FrontOfficeController;
use App\Http\Controllers\Backend\BirthDeathRecordController;
use App\Http\Controllers\Backend\HumanResourceController;
use App\Http\Controllers\Backend\DutyRoasterController;
use App\Http\Controllers\Backend\AnnualCalendarController;
use App\Http\Controllers\Backend\ApplyLeaveController;
use App\Http\Controllers\Backend\ReferralController;
use App\Http\Controllers\Backend\FinanceController;
use App\Http\Controllers\Backend\InventoryController;
use App\Http\Controllers\Backend\CertificateController;
use App\Http\Controllers\Backend\ReportsController;
use App\Http\Controllers\Backend\SetupController;
use App\Http\Controllers\Backend\DesignationController;
use App\Http\Controllers\Backend\DepartmentController;
use App\Http\Controllers\Backend\SpecialistController;
use App\Http\Controllers\Backend\BloodIssueController;
use App\Http\Controllers\Backend\BloodComponentIssueController;
use App\Http\Controllers\Backend\BedTypeController;
use App\Http\Controllers\Backend\BedGroupController;
use App\Http\Controllers\Backend\FloorController;
use App\Http\Controllers\Backend\BedController;
use App\Http\Controllers\Backend\PathologyTestController;
use App\Http\Controllers\Backend\PathologyCategoryController;
use App\Http\Controllers\Backend\PathologyUnitController;
use App\Http\Controllers\Backend\PathologyParameterController;
use App\Http\Controllers\Backend\ChargeController;
use App\Http\Controllers\Backend\ChargeCategoryController;
use App\Http\Controllers\Backend\ChargeTypeController;
use App\Http\Controllers\Backend\ChargeTaxCategoryController;
use App\Http\Controllers\Backend\ChargeUnitTypeController;
use App\Http\Controllers\Backend\DefineLeaveController;
use App\Http\Controllers\Backend\MedicineGroupController;
use App\Http\Controllers\Backend\MedicineCompanyController;
use App\Http\Controllers\Backend\MedicineUnitController;
use App\Http\Controllers\Backend\DoseDurationController;
use App\Http\Controllers\Backend\InvoiceController;
use App\Http\Controllers\Backend\MedicineDoseIntervalController;
use App\Http\Controllers\Backend\MedicineDosageController;
use App\Http\Controllers\Backend\MedicineCategoryController;
use App\Http\Controllers\Backend\MedicineSupplierController;
use App\Http\Controllers\Backend\MedicineInventoryController;
use App\Http\Controllers\Backend\MedicinePurchaseController;
use App\Http\Controllers\Backend\SupplierPaymentController;
use App\Http\Controllers\Backend\ProductReturnController;
use App\Http\Controllers\Backend\StockManagementController;
use App\Http\Controllers\Backend\ReferralCategoryController;

use App\Http\Controllers\Backend\ReferralPersonController;


use App\Http\Controllers\Backend\InvoiceDesignController;
use App\Http\Controllers\Backend\LeaveTypeController;
use App\Http\Controllers\Backend\ReportController;
use App\Http\Controllers\Backend\WebSettingController;

use App\Http\Controllers\Backend\PharmacyBillController;
use App\Http\Controllers\Backend\StaffAttendanceController;

use App\Http\Controllers\Backend\ExpenseHeadController;


use App\Http\Controllers\Backend\ExpenseController;

use App\Http\Controllers\Backend\DueCollectController;
use App\Http\Controllers\Backend\BillingDoctorController;
use App\Http\Controllers\Backend\ChargeImportController;
use App\Http\Controllers\Backend\FinanceReportController;

//don't remove this comment from route namespace

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [LoginController::class, 'loginPage'])->name('home')->middleware('AuthCheck');

Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    Artisan::call('clear-compiled');
    Artisan::call('optimize:clear');
    Artisan::call('storage:link');
    Artisan::call('optimize');
    session()->flash('message', 'System Updated Successfully.');

    return redirect()->route('backend.dashboard');
});

Route::group(['as' => 'auth.'], function () {
    Route::get('/login', [LoginController::class, 'loginPage'])->name('login2')->middleware('AuthCheck');
    Route::post('/login', [LoginController::class, 'login'])->name('login');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::group(['middleware' => 'AdminAuth'], function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('admin', AdminController::class);
    Route::get('admin/{id}/status/{status}/change', [AdminController::class, 'changeStatus'])->name('admin.status.change');

    // for role
    Route::resource('role', RoleController::class);

    // for permission entry
    Route::resource('permission', PermissionController::class);

    //for Patient
    Route::resource('patient', PatientController::class);
    Route::get('patient/{id}/status/{status}/change', [PatientController::class, 'changeStatus'])->name('patient.status.change');


    //for Tpa
    Route::resource('tpa', TpaController::class);
    Route::get('tpa/{id}/status/{status}/change', [TpaController::class, 'changeStatus'])->name('tpa.status.change');


    //for Billing
    Route::resource('billing', BillingController::class);
    Route::get('billing/{id}/status/{status}/change', [BillingController::class, 'changeStatus'])->name('billing.status.change');
    Route::get('billing', [BillingController::class, 'billing'])->name('billing.Page');
    Route::get('view-billing-page', [BillingController::class, 'billingPage'])->name('billing.view');
    Route::get('pending-billings', [BillingController::class, 'pendingList'])->name('pending.list');

    Route::get('view-billing-list-page', [BillingController::class, 'index'])->name('billing.list');
    Route::match(['post', 'put'], '/print/bill', [BillingController::class, 'printBill'])->name('print.bill');

    Route::get('/invoice/{id}', [BillingController::class, 'invoice'])->name('invoice');
    Route::get('/download-invoice', [InvoiceController::class, 'downloadInvoice'])->name('download.invoice');
    Route::get('/search/billing', [BillingController::class, 'searchShow'])->name('billing.search');

    Route::get('/billing/doctors/search', [BillingController::class, 'searchDoctors'])->name('billing.doctors.search');
    Route::post('/billing/doctors/create', [BillingController::class, 'createBillingDoctor'])->name('billing.doctors.create');

    //for Appoinment
    Route::resource('appoinment', AppoinmentController::class);
    Route::get('appoinment/{id}/status/{status}/change', [AppoinmentController::class, 'changeStatus'])->name('appoinment.status.change');
    Route::post('/doctors', [AppoinmentController::class, 'doctorStore'])->name('doctors.store');
    Route::get('/download/appointment/invoice', [InvoiceController::class, 'downloadAppointmentInvoice'])->name('download.appointment.invoice');

    //for OpdPatient
    Route::resource('opdpatient', OpdPatientController::class);
    Route::get('opdpatient/{id}/status/{status}/change', [OpdPatientController::class, 'changeStatus'])->name('opdpatient.status.change');
    Route::get('/download-opd-bill-print', [InvoiceController::class, 'downloadOpdInvoice'])->name('download.opd.bill');


    //for IpdPatient
    Route::resource('ipdpatient', IpdPatientController::class);
    Route::get('ipdpatient/{id}/status/{status}/change', [IpdPatientController::class, 'changeStatus'])->name('ipdpatient.status.change');


    //for Pharmacy
    Route::resource('pharmacy', PharmacyController::class);
    Route::get('pharmacy/{id}/status/{status}/change', [PharmacyController::class, 'changeStatus'])->name('pharmacy.status.change');


    //for Pathology
    Route::resource('pathology', PathologyController::class);
    Route::get('pathology/{id}/status/{status}/change', [PathologyController::class, 'changeStatus'])->name('pathology.status.change');


    //for Radiology
    Route::resource('radiology', RadiologyController::class);
    Route::get('radiology/{id}/status/{status}/change', [RadiologyController::class, 'changeStatus'])->name('radiology.status.change');


    //for BloodBank
    Route::resource('bloodbank', BloodBankController::class);
    Route::get('bloodbank/{id}/status/{status}/change', [BloodBankController::class, 'changeStatus'])->name('bloodbank.status.change');


    //for Ambulance
    Route::resource('ambulance', AmbulanceController::class);
    Route::get('ambulance/{id}/status/{status}/change', [AmbulanceController::class, 'changeStatus'])->name('ambulance.status.change');


    //for FrontOffice
    Route::resource('frontoffice', FrontOfficeController::class);
    Route::get('frontoffice/{id}/status/{status}/change', [FrontOfficeController::class, 'changeStatus'])->name('frontoffice.status.change');


    //for BirthDeathRecord
    Route::resource('birthdeathrecord', BirthDeathRecordController::class);
    Route::get('birthdeathrecord/{id}/status/{status}/change', [BirthDeathRecordController::class, 'changeStatus'])->name('birthdeathrecord.status.change');

    //for DutyRoaster
    Route::resource('dutyroaster', DutyRoasterController::class);
    Route::get('dutyroaster/{id}/status/{status}/change', [DutyRoasterController::class, 'changeStatus'])->name('dutyroaster.status.change');


    //for AnnualCalendar
    Route::resource('annualcalendar', AnnualCalendarController::class);
    Route::get('annualcalendar/{id}/status/{status}/change', [AnnualCalendarController::class, 'changeStatus'])->name('annualcalendar.status.change');


    //for Referral
    Route::resource('referral', ReferralController::class);
    Route::get('referral/{id}/status/{status}/change', [ReferralController::class, 'changeStatus'])->name('referral.status.change');
    Route::post('referral/commission/preview', [ReferralController::class, 'commissionPreview'])->name('referral.commission.preview');


    //for Inventory
    Route::resource('inventory', InventoryController::class);
    Route::get('inventory/{id}/status/{status}/change', [InventoryController::class, 'changeStatus'])->name('inventory.status.change');


    //for Certificate
    Route::resource('certificate', CertificateController::class);
    Route::get('certificate/{id}/status/{status}/change', [CertificateController::class, 'changeStatus'])->name('certificate.status.change');


    //for Reports
    Route::resource('reports', ReportsController::class);
    Route::get('reports/{id}/status/{status}/change', [ReportsController::class, 'changeStatus'])->name('reports.status.change');


    //for Setup
    Route::resource('setup', SetupController::class);
    Route::get('setup/{id}/status/{status}/change', [SetupController::class, 'changeStatus'])->name('setup.status.change');


    //for Designation
    Route::resource('designation', DesignationController::class);
    Route::get('designation/{id}/status/{status}/change', [DesignationController::class, 'changeStatus'])->name('designation.status.change');


    //for Department
    Route::resource('department', DepartmentController::class);
    Route::get('department/{id}/status/{status}/change', [DepartmentController::class, 'changeStatus'])->name('department.status.change');


    //for Specialist
    Route::resource('specialist', SpecialistController::class);
    Route::get('specialist/{id}/status/{status}/change', [SpecialistController::class, 'changeStatus'])->name('specialist.status.change');


    //for BloodIssue
    Route::resource('bloodissue', BloodIssueController::class);
    Route::get('bloodissue/{id}/status/{status}/change', [BloodIssueController::class, 'changeStatus'])->name('bloodissue.status.change');


    //for BloodComponentIssue
    Route::resource('bloodcomponentissue', BloodComponentIssueController::class);
    Route::get('bloodcomponentissue/{id}/status/{status}/change', [BloodComponentIssueController::class, 'changeStatus'])->name('bloodcomponentissue.status.change');


    //for BedType
    Route::resource('bedtype', BedTypeController::class);
    Route::get('bedtype/{id}/status/{status}/change', [BedTypeController::class, 'changeStatus'])->name('bedtype.status.change');


    //for BedGroup
    Route::resource('bedgroup', BedGroupController::class);
    Route::get('bedgroup/{id}/status/{status}/change', [BedGroupController::class, 'changeStatus'])->name('bedgroup.status.change');


    //for Floor
    Route::resource('floor', FloorController::class);
    Route::get('floor/{id}/status/{status}/change', [FloorController::class, 'changeStatus'])->name('floor.status.change');


    //for Bed
    Route::resource('bed', BedController::class);
    Route::get('bed/{id}/status/{status}/change', [BedController::class, 'changeStatus'])->name('bed.status.change');


    //for PathologyTest
    Route::resource('testpathology', PathologyTestController::class);
    Route::get('testpathology/{id}/status/{status}/change', [PathologyTestController::class, 'changeStatus'])->name('testpathology.status.change');


    //for PathologyCategory
    Route::resource('pathologycategory', PathologyCategoryController::class);
    Route::get('pathologycategory/{id}/status/{status}/change', [PathologyCategoryController::class, 'changeStatus'])->name('pathologycategory.status.change');


    //for PathologyUnit
    Route::resource('pathologyunit', PathologyUnitController::class);
    Route::get('pathologyunit/{id}/status/{status}/change', [PathologyUnitController::class, 'changeStatus'])->name('pathologyunit.status.change');


    //for PathologyParameter
    Route::resource('parameterofpathology', PathologyParameterController::class);
    Route::get('parameterofpathology/{id}/status/{status}/change', [PathologyParameterController::class, 'changeStatus'])->name('parameterofpathology.status.change');


    //for Charge
    Route::resource('hospitalcharge', ChargeController::class);
    Route::get('hospitalcharge/{id}/status/{status}/change', [ChargeController::class, 'changeStatus'])->name('hospitalcharge.status.change');


    //for ChargeCategory
    Route::resource('chargecategory', ChargeCategoryController::class);
    Route::get('chargecategory/{id}/status/{status}/change', [ChargeCategoryController::class, 'changeStatus'])->name('chargecategory.status.change');


    //for ChargeType
    Route::resource('chargetype', ChargeTypeController::class);
    Route::get('chargetype/{id}/status/{status}/change', [ChargeTypeController::class, 'changeStatus'])->name('chargetype.status.change');
    Route::post('chargetype/toggle-module', [ChargeTypeController::class, 'toggleModule'])->name('chargetype.toggle-module');


    //for ChargeTaxCategory
    Route::resource('chargetaxcategory', ChargeTaxCategoryController::class);
    Route::get('chargetaxcategory/{id}/status/{status}/change', [ChargeTaxCategoryController::class, 'changeStatus'])->name('chargetaxcategory.status.change');


    //for ChargeUnitType
    Route::resource('chargeunittype', ChargeUnitTypeController::class);
    Route::get('chargeunittype/{id}/status/{status}/change', [ChargeUnitTypeController::class, 'changeStatus'])->name('chargeunittype.status.change');


    //for MedicineGroup
    Route::resource('medicinegroup', MedicineGroupController::class);
    Route::get('medicinegroup/{id}/status/{status}/change', [MedicineGroupController::class, 'changeStatus'])->name('medicinegroup.status.change');


    //for MedicineCompany
    Route::resource('medicinecompany', MedicineCompanyController::class);
    Route::get('medicinecompany/{id}/status/{status}/change', [MedicineCompanyController::class, 'changeStatus'])->name('medicinecompany.status.change');


    //for MedicineUnit
    Route::resource('medicineunit', MedicineUnitController::class);
    Route::get('medicineunit/{id}/status/{status}/change', [MedicineUnitController::class, 'changeStatus'])->name('medicineunit.status.change');


    //for DoseDuration
    Route::resource('doseduration', DoseDurationController::class);
    Route::get('doseduration/{id}/status/{status}/change', [DoseDurationController::class, 'changeStatus'])->name('doseduration.status.change');


    //for MedicineDoseInterval
    Route::resource('medicinedoseinterval', MedicineDoseIntervalController::class);
    Route::get('medicinedoseinterval/{id}/status/{status}/change', [MedicineDoseIntervalController::class, 'changeStatus'])->name('medicinedoseinterval.status.change');


    //for MedicineDosage
    Route::resource('medicinedosage', MedicineDosageController::class);
    Route::get('medicinedosage/{id}/status/{status}/change', [MedicineDosageController::class, 'changeStatus'])->name('medicinedosage.status.change');


    //for MedicineCategory
    Route::resource('medicinecategory', MedicineCategoryController::class);
    Route::get('medicinecategory/{id}/status/{status}/change', [MedicineCategoryController::class, 'changeStatus'])->name('medicinecategory.status.change');


    //for MedicineSupplier
    Route::resource('medicinesupplier', MedicineSupplierController::class);
    Route::get('medicinesupplier/{id}/status/{status}/change', [MedicineSupplierController::class, 'changeStatus'])->name('medicinesupplier.status.change');


    // for MedicineInventory
    Route::resource(
        'medicineinventory',
        MedicineInventoryController::class
    );

    Route::get(
        'medicineinventory/{id}/status/{status}/change',
        [MedicineInventoryController::class, 'changeStatus']
    )->name('medicineinventory.status.change');

    Route::post(
        'medicineinventory/import-csv',
        [MedicineInventoryController::class, 'importCsv']
    )->name('medicineinventory.import.csv');

    // for MedicinePurchase
    Route::resource('medicinepurchase', \App\Http\Controllers\Backend\MedicinePurchaseController::class);
    Route::post('medicinepurchase/{medicinepurchase}/receive', [\App\Http\Controllers\Backend\MedicinePurchaseController::class, 'receiveItems'])->name('medicinepurchase.receive');

    // for SupplierPayment
    Route::resource('supplierpayment', \App\Http\Controllers\Backend\SupplierPaymentController::class);
    Route::post('supplierpayment/{supplierpayment}/partial', [\App\Http\Controllers\Backend\SupplierPaymentController::class, 'addPartialPayment'])->name('supplierpayment.partial');
    Route::get('supplierpayment/report/stock-due', [\App\Http\Controllers\Backend\SupplierPaymentController::class, 'stockDueReport'])->name('supplierpayment.report.stock-due');

    // for ProductReturn
    Route::resource('productreturn', \App\Http\Controllers\Backend\ProductReturnController::class);
    Route::post('productreturn/{productreturn}/approve', [\App\Http\Controllers\Backend\ProductReturnController::class, 'approve'])->name('productreturn.approve');
    Route::post('productreturn/{productreturn}/process', [\App\Http\Controllers\Backend\ProductReturnController::class, 'process'])->name('productreturn.process');

    // for StockManagement
    Route::get('stock', [\App\Http\Controllers\Backend\StockManagementController::class, 'index'])->name('stock.index');
    Route::get('stock/adjustments', [\App\Http\Controllers\Backend\StockManagementController::class, 'adjustments'])->name('stock.adjustments');
    Route::get('stock/adjustment/create', [\App\Http\Controllers\Backend\StockManagementController::class, 'createAdjustment'])->name('stock.adjustment.create');
    Route::post('stock/adjustment', [\App\Http\Controllers\Backend\StockManagementController::class, 'storeAdjustment'])->name('stock.adjustment.store');
    Route::get('stock/low-stock-report', [\App\Http\Controllers\Backend\StockManagementController::class, 'lowStockReport'])->name('stock.low-stock-report');
    Route::get('stock/movement-report', [\App\Http\Controllers\Backend\StockManagementController::class, 'stockMovementReport'])->name('stock.movement-report');

    //for ReferralCategory
    Route::resource('referralcategory', ReferralCategoryController::class);
    Route::get('referralcategory/{id}/status/{status}/change', [ReferralCategoryController::class, 'changeStatus'])->name('referralcategory.status.change');


    //for ReferralPerson
    Route::resource('referralperson', ReferralPersonController::class);
    Route::get('referralperson/{id}/status/{status}/change', [ReferralPersonController::class, 'changeStatus'])->name('referralperson.status.change');


    //for InvoiceDesign
    Route::resource('invoicedesign', InvoiceDesignController::class);
    Route::get('invoicedesign/{id}/status/{status}/change', [InvoiceDesignController::class, 'changeStatus'])->name('invoicedesign.status.change');

    //for report
    Route::get('all-report', [ReportController::class, 'index'])->name('report.index');
    Route::get('/report/generate-pdf', [ReportController::class, 'generatePdf'])->name('report.generate-pdf');

    // for WebSetting
    Route::get('websetting-create', [WebSettingController::class, 'create'])->name('websetting.create');
    Route::match(['get', 'post'], 'websetting-store', [WebSettingController::class, 'store'])->name('websetting.store');

    //for PharmacyBill
    Route::resource('pharmacybill', PharmacyBillController::class);
    Route::get('pharmacybill/{id}/status/{status}/change', [PharmacyBillController::class, 'changeStatus'])->name('pharmacybill.status.change');

    // for StaffAttendance
    Route::resource('staffattendance', StaffAttendanceController::class);
    Route::get('staffattendance/{id}/status/{status}/change', [StaffAttendanceController::class, 'changeStatus'])->name('staffattendance.status.change');
    Route::get('/backend/staffattendance/fetch', [StaffAttendanceController::class, 'fetchDate'])->name('staffattendance.fetch');
    Route::get('/backend/staffattendance/report', [StaffAttendanceController::class, 'attendanceReport'])->name('staffattendance.report');
    Route::get('/staffattendance/report/{id}', [StaffAttendanceController::class, 'attendanceReportDetails'])->name('staffattendance.report.details');
    Route::get('/staff/payslip/{id}', [StaffAttendanceController::class, 'staffPaySlip'])->name('staff.payslip');
    Route::get('/payslip/download', [StaffAttendanceController::class, 'download'])->name('download.payslip');

    // for LeaveType
    Route::resource('Leavetype', LeaveTypeController::class);
    Route::get('approval-leave-request', [LeaveTypeController::class, 'approvalRequest'])->name('approval.request');
    Route::get('pending-leave-request', [LeaveTypeController::class, 'pendingRequest'])->name('pending.request');
    Route::get('all-apply-list', [LeaveTypeController::class, 'applyList'])->name('apply.list');
    Route::post('add-leave-type', [LeaveTypeController::class, 'storeLeaveType'])->name('store.leave.type');
    Route::get('pending/leave/{id}', [LeaveTypeController::class, 'pendingLeave'])->name('pending.leave');
    Route::get('approve/leave/{id}', [LeaveTypeController::class, 'approveLeave'])->name('approve.leave');
    Route::get('approve/leave/confirm/{id}', [LeaveTypeController::class, 'confirmApproval'])->name('approved.leave.confirm');
    Route::get('reject/leave/{id}', [LeaveTypeController::class, 'rejectLeave'])->name('reject.leave');
    Route::get('view/leave/list/{id}', [LeaveTypeController::class, 'leaveListView'])->name('leave.list.view');
    Route::get('Leavetype/{id}/status/{status}/change', [LeaveTypeController::class, 'changeStatus'])->name('Leavetype.status.change');

    // for DefineLeave
    Route::resource('defineleave', DefineLeaveController::class);
    Route::get('defineleave/{id}/status/{status}/change', [DefineLeaveController::class, 'changeStatus'])->name('defineleave.status.change');

    // for ApplyLeave
    Route::resource('applyleave', ApplyLeaveController::class);
    Route::get('applyleave/{id}/status/{status}/change', [ApplyLeaveController::class, 'changeStatus'])->name('applyleave.status.change');

    //for ExpenseHead
    Route::resource('expensehead', ExpenseHeadController::class);
    Route::get('expensehead/{id}/status/{status}/change', [ExpenseHeadController::class, 'changeStatus'])->name('expensehead.status.change');


    //for Expense
    Route::resource('expense', ExpenseController::class);
    Route::get('expense/{id}/status/{status}/change', [ExpenseController::class, 'changeStatus'])->name('expense.status.change');


    //for BillingDoctor
    Route::resource('billingdoctor', BillingDoctorController::class);
    Route::get('billingdoctor/{id}/status/{status}/change', [BillingDoctorController::class, 'changeStatus'])->name('billingdoctor.status.change');


    //bulk import
    Route::get('/charges/import', [ChargeImportController::class, 'showImportForm'])->name('charges.import.form');
    Route::post('/charges/import', [ChargeImportController::class, 'processImport'])->name('charges.import.process');
    Route::get('/charges/import/sample-csv', [ChargeImportController::class, 'downloadSampleCsv'])->name('charges.import.sample');

    //for finance report
    Route::get('finance/report', [FinanceReportController::class, 'reportPage'])->name('finance-report.index');
    Route::get('/finance/report/download-pdf', [FinanceReportController::class, 'downloadPDF'])->name('finance.report.pdf');



    //Route::middleware(['auth:admin'])->group(function () {

    // Pending billings
    //Route::get('pending-billings', [BillingController::class, 'pending'])
       // ->name('pending.billings');

    // Due collect form
    Route::get('due-collect/{id}', [DueCollectController::class, 'index'])
        ->name('backend.backend.due.collect');

    // Due collect submit
    Route::post('due-collect/{id}', [DueCollectController::class, 'store'])
        ->name('backend.backend.due.collect.store');
    //});
 
});
