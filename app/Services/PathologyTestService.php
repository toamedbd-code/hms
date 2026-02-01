<?php

namespace App\Services;
use App\Models\PathologyTest;

class PathologyTestService
{
    protected $pathologytestModel;

    public function __construct(PathologyTest $pathologytestModel)
    {
        $this->pathologytestModel = $pathologytestModel;
    }

    public function list()
    {
        return  $this->pathologytestModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->pathologytestModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->pathologytestModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->pathologytestModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->pathologytestModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->pathologytestModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id, $status)
    {
        $dataInfo =  $this->pathologytestModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->pathologytestModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();
    }


    public function activeList()
    {
        return  $this->pathologytestModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

    // public function create(array $data)
    // {
    //     DB::beginTransaction();

    //     try {
    //         // Create the pathology test
    //         $pathologyTest = PathologyTest::create([
    //             'test_name' => $data['test_name'],
    //             'test_short_name' => $data['test_short_name'] ?? null,
    //             'test_type' => $data['test_type'] ?? null,
    //             'pathology_category_id' => $data['pathology_category_id'],
    //             'pathology_sub_category' => $data['pathology_sub_category'] ?? null,
    //             'method' => $data['method'] ?? null,
    //             'report_days' => $data['report_days'] ?? null,
    //             'charge_category_id' => $data['charge_category_id'] ?? null,
    //             'charge_name' => $data['charge_name'] ?? null,
    //             'tax' => $data['tax'] ?? null,
    //             'standard_charge' => $data['standard_charge'] ?? null,
    //             'amount' => $data['amount'] ?? null,
    //             'test_parameters' => json_encode($data['parameters']), // Store as JSON
    //             'status' => 'Active'
    //         ]);

    //         // Also store in separate table for relational queries
    //         if (isset($data['parameters']) && is_array($data['parameters'])) {
    //             foreach ($data['parameters'] as $parameter) {
    //                 PathologyTestParameter::create([
    //                     'pathology_test_id' => $pathologyTest->id,
    //                     'test_parameter_id' => $parameter['test_parameter_id'] ?? null,
    //                     'name' => $parameter['name'] ?? null,
    //                     'reference_from' => $parameter['referance_from'] ?? null,
    //                     'reference_to' => $parameter['referance_to'] ?? null,
    //                     'pathology_unit_id' => $parameter['pathology_unit_id'] ?? null,
    //                 ]);
    //             }
    //         }

    //         DB::commit();
    //         return $pathologyTest;
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    // public function update(array $data, $id)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $pathologyTest = PathologyTest::findOrFail($id);

    //         // Update main record
    //         $pathologyTest->update([
    //             'test_name' => $data['test_name'],
    //             'test_short_name' => $data['test_short_name'] ?? null,
    //             'test_type' => $data['test_type'] ?? null,
    //             'pathology_category_id' => $data['pathology_category_id'],
    //             'pathology_sub_category' => $data['pathology_sub_category'] ?? null,
    //             'method' => $data['method'] ?? null,
    //             'report_days' => $data['report_days'] ?? null,
    //             'charge_category_id' => $data['charge_category_id'] ?? null,
    //             'charge_name' => $data['charge_name'] ?? null,
    //             'tax' => $data['tax'] ?? null,
    //             'standard_charge' => $data['standard_charge'] ?? null,
    //             'amount' => $data['amount'] ?? null,
    //             'test_parameters' => json_encode($data['parameters']), // Update JSON
    //         ]);

    //         // Delete existing parameters and recreate
    //         PathologyTestParameter::where('pathology_test_id', $id)->delete();

    //         if (isset($data['parameters']) && is_array($data['parameters'])) {
    //             foreach ($data['parameters'] as $parameter) {
    //                 PathologyTestParameter::create([
    //                     'pathology_test_id' => $pathologyTest->id,
    //                     'test_parameter_id' => $parameter['test_parameter_id'] ?? null,
    //                     'name' => $parameter['name'] ?? null,
    //                     'reference_from' => $parameter['referance_from'] ?? null,
    //                     'reference_to' => $parameter['referance_to'] ?? null,
    //                     'pathology_unit_id' => $parameter['pathology_unit_id'] ?? null,
    //                 ]);
    //             }
    //         }

    //         DB::commit();
    //         return $pathologyTest;
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }
}
