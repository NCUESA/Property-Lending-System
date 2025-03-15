# 器材外借網站

### 為了讓器材借用能更方便，所以寫ㄉ

目前已完成

## 版本
- Ver 1.3
- 最近Commit日期：2025/3/14
- Commit Name：Issue 2 Fixed
- 更新內容：
    1. 現在可以在借還清單看到每個借用狀態了，綠色代表借用中，灰色代表已經順利歸還，藍色代表未借出，紅色代表有人在破壞系統
    2. 完成借用後頁面不會整個再重新整理，而是會刷新頁面內容
    3. 修正過往借用紀錄不會出現的問題
    4. 修正進階借用與快速查詢排序不同的問題
    5. 修正未借出物品退還系統的狀態問題，未被借出的物品會被標註為退還系統，且會顯示在借用清單上
    6. 過往的順利歸還紀錄會被標註為紅色，這是正常現象
    7. 修正已經歸還的清單可以幫別人還器材的bug

## 開發人員資訊
- Email:  ncuesa@gm.ncue.edu.tw
- Dev:    Enderman

# 套件版本
- Laravel 11
- PHP 8

# 歷史紀錄

- Ver 1.2.3
- 最近Commit日期：2025/3/10
- Commit Name：Small Hot Fixed Fixed Fixed
- 更新內容：
    1. 修正表單篩選只會出現進德的Bug
       
- Ver 1.2.2
- 最近Commit日期：2025/3/10
- Commit Name：Small Hot Fixed Fixed 
- 更新內容：
    1. 調整表單篩選條件，改為下拉式選單
    2. 修正器材改動時，取得日期的型態錯誤

- Ver 1.2.1
- 最近Commit日期：2025/3/10
- Commit Name：Status Hot Fixed
- 更新內容：
    1. 修正器材清單重複出現的Bug
       
- Ver 1.2.0
- 最近Commit日期：2025/3/10
- Commit Name：Little Bug Fixed
- 更新內容：
    1. 修正表單送出不會跳出完成的Bug
    2. 借用表單現在可以用搜尋尋找想要的器材
    3. 修正網頁上說明文字
    5. 身分認證改變，預留串接SAO的欄位
    6. Bug Fixed
       
- Ver 1.0.6
- 最近Commit日期：2025/1/22
- Commit Name：Auth Hot Fixed
- 更新內容：
    1. 刪除Session認證機制
    2. 調整身分認證機制
    3. Bug Fixed

- Ver 1.0.5
- 最近Commit日期：2025/1/21
- Commit Name：RWD Optimized
- 更新內容：
    1. 修正Session登入權限問題
    2. 修改Status Table呈現方式
    3. UI調整
       
- Ver 1.0.4
- 最近Commit日期：2025/1/20
- Commit Name：BorrowPage Table Remake
- 更新內容：
    1. 重製借用頁面器材列表呈現方式
    2. 按鈕增加圖示，提升辨別度
       
- Ver 1.0.3
- 最近Commit日期：2024/11/6
- Commit Name：Frontend Optimized
- 更新內容：
    1. 修正表單提交未檢查的bug
    2. 修正值勤人員控管頁面，簡化版面
    3. 值勤人員頁面新增查詢功能 
