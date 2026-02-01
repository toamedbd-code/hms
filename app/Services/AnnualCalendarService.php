<?php
namespace App\Services;
use App\Models\AnnualCalendar;

class AnnualCalendarService
{
    protected $AnnualCalendarModel;

    public function __construct(AnnualCalendar $annualcalendarModel)
    {
        $this->annualcalendarModel = $annualcalendarModel;
    }

    public function list()
    {
        return  $this->annualcalendarModel->whereNull('deleted_at');
    }

    public function all()
    {
        return  $this->annualcalendarModel->whereNull('deleted_at')->all();
    }

    public function find($id)
    {
        return  $this->annualcalendarModel->find($id);
    }

    public function create(array $data)
    {
        return  $this->annualcalendarModel->create($data);
    }

    public function update(array $data, $id)
    {
        $dataInfo =  $this->annualcalendarModel->findOrFail($id);

        $dataInfo->update($data);

        return $dataInfo;
    }

    public function delete($id)
    {
        $dataInfo =  $this->annualcalendarModel->find($id);

        if (!empty($dataInfo)) {

            $dataInfo->deleted_at = date('Y-m-d H:i:s');

            $dataInfo->status = 'Deleted';

            return ($dataInfo->save());
        }
        return false;
    }

    public function changeStatus($id,$status)
    {
        $dataInfo =  $this->annualcalendarModel->findOrFail($id);
        $dataInfo->status = $status;
        $dataInfo->update();

        return $dataInfo;
    }

    public function AdminExists($userName)
    {
        return  $this->annualcalendarModel->whereNull('deleted_at')
            ->where(function ($q) use ($userName) {
                $q->where('email', strtolower($userName))
                    ->orWhere('phone', $userName);
            })->first();

    }


    public function activeList()
    {
        return  $this->annualcalendarModel->whereNull('deleted_at')->where('status', 'Active')->get();
    }

}

