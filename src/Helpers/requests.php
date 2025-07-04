<?php

if (!function_exists('parseDataWithMultiForm')) {
    function parseDataWithMultiForm($key)
    {
        $requestData = request()->all();

        if (isset($requestData[$key])) {
            $requestData[$key] = collect($requestData[$key])->map(function ($detail) {
                return array_merge($detail, ['id' => $detail['multiFormKey'] ?? null]);
            })->toArray();
        }

        return $requestData;
    }
}
