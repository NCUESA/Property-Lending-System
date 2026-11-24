<?php

use App\Support\PropertyImage;

test('舊系統匯入的檔名補上 properties 資料夾', function () {
    expect(PropertyImage::url('40.jpg'))->toBe(asset('storage/properties/40.jpg'));
});

test('已含資料夾的路徑照原樣', function () {
    expect(PropertyImage::url('properties/abc.jpg'))->toBe(asset('storage/properties/abc.jpg'));
});

test('沒有照片時回傳 null', function () {
    expect(PropertyImage::url(null))->toBeNull()
        ->and(PropertyImage::url(''))->toBeNull();
});
