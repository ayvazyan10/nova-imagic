<?php

use Ayvazyan10\Imagic\Http\Controllers\MediaAssetController;
use Ayvazyan10\Imagic\Http\Controllers\MediaContentController;
use Ayvazyan10\Imagic\Http\Controllers\MediaFolderController;
use Illuminate\Support\Facades\Route;

Route::get('media', [MediaAssetController::class, 'index'])->name('media.index');
Route::post('media', [MediaAssetController::class, 'store'])->name('media.store');
Route::post('media/bulk-delete', [MediaAssetController::class, 'bulkDestroy'])->name('media.bulk-destroy');
Route::patch('media/{media}', [MediaAssetController::class, 'update'])->name('media.update');
Route::delete('media/{media}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
Route::get('media/{media}/content', [MediaContentController::class, 'content'])->name('media.content');
Route::get('media/{media}/thumbnail', [MediaContentController::class, 'thumbnail'])->name('media.thumbnail');

Route::post('folders', [MediaFolderController::class, 'store'])->name('folders.store');
Route::patch('folders/{folder}', [MediaFolderController::class, 'update'])->name('folders.update');
Route::delete('folders/{folder}', [MediaFolderController::class, 'destroy'])->name('folders.destroy');
