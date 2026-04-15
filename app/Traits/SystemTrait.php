<?php

namespace App\Traits;

use Illuminate\Support\Str;
use App\Models\SystemErrorLog;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Storage;

trait SystemTrait
{

    /**
     * @param Request $request
     * @return $this|false|string
     */


    public function successResponse($message, $url, $data)
    {

        return [
            'message' => $message,
            'redirectUrl' => $url,
            'data' => $data,
            'status' => true,
        ];
    }
    public function warningResponse($message, $url, $data)
    {

        return [
            'warningMessage' => $message,
            'redirectUrl' => $url,
            'data' => $data,
            'status' => false,
        ];
    }
    public function errorResponse($message, $url, $data)
    {

        return [
            'errorMessage' => $message,
            'redirectUrl' => $url,
            'data' => $data,
            'status' => false,
        ];
    }

    public function storeAdminWorkLog($dataId, $referenceTable, $note)
    {

        $data = [
            'data_id' => $dataId,
            'admin_id' => auth('admin')->user()->id,
            'reference_table' => $referenceTable,
            'note' => $note,
            'created_at' => currentTimeStamp(),
        ];

        SystemLog::create($data);
    }


    public function storeSystemError($namespace, $controller, $function, $log)
    {

        $data = [
            'namespace' => $namespace,
            'controller' => $controller,
            'function' => $function,
            'log' => $log,
            'created_at' => currentTimeStamp(),
        ];

        SystemErrorLog::create($data);
    }

    public function imageUpload($image, $folder)
    {
        $folder = trim((string) $folder, '/');
        if ($folder === '') {
            throw new \InvalidArgumentException('Upload folder is missing.');
        }

        if (!$image || !method_exists($image, 'isValid') || !$image->isValid()) {
            throw new \InvalidArgumentException('Upload file is missing or invalid.');
        }

        if (method_exists($image, 'getSize') && (int) $image->getSize() <= 0) {
            throw new \InvalidArgumentException('Upload file is empty.');
        }

        $realPath = method_exists($image, 'getRealPath') ? (string) $image->getRealPath() : '';
        if ($realPath === '' || !is_readable($realPath)) {
            throw new \InvalidArgumentException('Upload file path is missing.');
        }

        $originalName = (string) $image->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseSlug = Str::slug($baseName);
        if ($baseSlug === '') {
            $baseSlug = 'upload';
        }

        $extension = (string) $image->getClientOriginalExtension();
        if ($extension === '') {
            $extension = (string) $image->extension();
        }
        if ($extension === '') {
            $extension = 'bin';
        }

        $imageName = $baseSlug . '-' . uniqid() . '.' . $extension;

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $image->storeAs($folder, $imageName, 'public');
        return $folder . '/' . $imageName;
    }
    
    public function fileUpload($image, $folder)
    {
        $folder = trim((string) $folder, '/');
        if ($folder === '') {
            throw new \InvalidArgumentException('Upload folder is missing.');
        }

        if (!$image || !method_exists($image, 'isValid') || !$image->isValid()) {
            throw new \InvalidArgumentException('Upload file is missing or invalid.');
        }

        if (method_exists($image, 'getSize') && (int) $image->getSize() <= 0) {
            throw new \InvalidArgumentException('Upload file is empty.');
        }

        $realPath = method_exists($image, 'getRealPath') ? (string) $image->getRealPath() : '';
        if ($realPath === '' || !is_readable($realPath)) {
            throw new \InvalidArgumentException('Upload file path is missing.');
        }

        $originalName = (string) $image->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseSlug = Str::slug($baseName);
        if ($baseSlug === '') {
            $baseSlug = 'upload';
        }

        $extension = (string) $image->getClientOriginalExtension();
        if ($extension === '') {
            $extension = (string) $image->extension();
        }
        if ($extension === '') {
            $extension = 'bin';
        }

        $imageName = $baseSlug . '-' . uniqid() . '.' . $extension;

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $image->storeAs($folder, $imageName, 'public');
        return $folder . '/' . $imageName;
    }

    public function storeDescription($dataId,$referenceTable,$description)
    {
        $index = 0;

        $breakPoint = 0;

        $segment = '';

        while ($index < strlen($description)) {

            $segment = $segment . $description[$index];

            $index++;

            $breakPoint++;

            if ($breakPoint == (strlen($description) - 1)) {

                $flag = $this->storeDescriptionSegment($dataId, $referenceTable, $segment);

                if (!$flag)
                    break;

                $breakPoint = 0;

                $segment = "";

                break;
            }

            if ($breakPoint == 10000) {

                $flag = $this->storeDescriptionSegment($dataId, $referenceTable, $segment);

                if (!$flag)
                    break;

                $breakPoint = 0;

                $segment = "";
            }
        }

        return $flag;
    }

    public function storeDescriptionSegment($dataId, $referenceTable, $segment)
    {
        // $data=[
        //     'data_id'=>$dataId,
        //     'reference_table'=>$referenceTable,
        //     'details_segment'=>$segment,
        // ];
        // $descriptionRepository=new DescriptionRepository();
        // return $descriptionRepository->create($data);
        return true;
    }
}
