<?php

use App\Http\Controllers\Api\Remittance\Corn;
use App\Http\Controllers\Api\Remittance\Soybean;
use App\Http\Controllers\Api\Submission;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
//    Route::apiResource('remittances/corn', Corn::class)
//        ->middleware(LogRequestResponse::class);
//
//    Route::apiResource('remittances/soybean', Soybean::class)
//        ->middleware(LogRequestResponse::class);
//
//    Route::apiResource('submissions', Submission::class)
//        ->middleware(LogRequestResponse::class);
});
