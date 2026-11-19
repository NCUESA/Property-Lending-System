<?php

// 只翻譯本專案用到的規則；未列出的規則會退回英文（fallback_locale）。
return [
    'accepted' => '請勾選:attribute。',
    'after_or_equal' => ':attribute 不能早於 :date。',
    'array' => ':attribute 格式不正確。',
    'before_or_equal' => ':attribute 不能晚於 :date。',
    'boolean' => ':attribute 必須是是或否。',
    'date' => ':attribute 不是有效的日期。',
    'date_equals' => ':attribute 必須是 :date。',
    'digits' => ':attribute 必須是 :digits 位數字。',
    'distinct' => ':attribute 有重複的值。',
    'email' => ':attribute 不是有效的電子郵件地址。',
    'exists' => '所選的:attribute 不存在。',
    'image' => ':attribute 必須是圖片。',
    'in' => '所選的:attribute 不正確。',
    'integer' => ':attribute 必須是整數。',
    'max' => [
        'file' => ':attribute 不能大於 :max KB。',
        'numeric' => ':attribute 不能大於 :max。',
        'string' => ':attribute 不能超過 :max 個字。',
        'array' => ':attribute 不能超過 :max 項。',
    ],
    'min' => [
        'file' => ':attribute 不能小於 :min KB。',
        'numeric' => ':attribute 不能小於 :min。',
        'string' => ':attribute 至少要 :min 個字。',
        'array' => ':attribute 至少要 :min 項。',
    ],
    'mimes' => ':attribute 必須是 :values 格式。',
    'numeric' => ':attribute 必須是數字。',
    'regex' => ':attribute 格式不正確。',
    'required' => '請填寫:attribute。',
    'required_without' => '請至少選擇一項:attribute。',
    'size' => [
        'string' => ':attribute 必須是 :size 個字元。',
    ],
    'string' => ':attribute 必須是文字。',
    'unique' => ':attribute 已經存在。',

    'attributes' => [
        'email' => '電子郵件',
        'name' => '名稱',
        'password' => '密碼',
        'student_id' => '學號',
    ],
];
