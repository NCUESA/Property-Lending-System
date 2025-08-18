const CONTROL_PANEL_URL = '/status_table/control';
const LIMIT_DATA_NUMBER = adjustLimitNumber();

class BorrowRecord {
    static STATUS_VALUE = Object.freeze({
        BACK_TO_SYS: 0,
        LENDING_OUT: 1,
        WAIT_FOR_LEND: 2,
        RETURNED: 3,
        OVERDUE: 4
    });
    constructor(record) {
        this.borrow_id = record.id
        this.filling_time = record.filling_time;
        this.borrow_date = record.borrow_date;
        this.returned_date = record.returned_date;
        this.borrow_department = record.borrow_department;
        this.borrow_person_name = record.borrow_person_name;
        this.phone = record.phone;
        this.email = record.email;

        this.status = record.status;
    }
}

// Records Data
let G_records = new Map();

$(document).ready(function () {
    reloadPage();


    // 地點查詢
    $('input[name="place"]').on('change', function () {
        const newLocation = $(this).val();
        console.log(newLocation);
        const currentPage = 1; // 選 radio 時從第一頁開始
        updateURL(currentPage, newLocation);
        reloadPage(page = 1, limit = LIMIT_DATA_NUMBER);
    });


    // 搜尋
    // 2025/08/14:
    // 目前搜尋沒有辦法做Pagination
    // 原因是因為reloadPage跟Pagination是Call不同的API
    // 兩者API在後端完全不一樣
    // 如果需要調整則需要大修後端
    // 但筆者現在懶得用
    // @ender
    // 改造搜尋提交
    $('#search').submit(function (e) {
        e.preventDefault();

        const params = new URLSearchParams(window.location.search);
        params.set('page', 1); // 搜尋結果從第 1 頁開始

        // 把搜尋欄位塞進 URL
        params.set('contact', $('#search_contact').val());
        params.set('property', $('#search_property').val());
        params.set('lendout_date', $('#search_lendout').val());
        params.set('return_date', $('#search_return').val());
        params.set('department', $('#search_department').val());
        params.set('prepare_return', $('#search_prepare_return').val());

        const url = new URL(window.location);
        url.search = params.toString();
        window.history.replaceState({}, '', url);

        reloadPage(1, LIMIT_DATA_NUMBER);
    });

    $('input[name="classStatusRadio"]').on('change', function () {
        const params = new URLSearchParams(window.location.search);
        params.set('page', 1); // 搜尋結果從第 1 頁開始

        const codeConverter = {
            "waiting": BorrowRecord.STATUS_VALUE.WAIT_FOR_LEND,
            "lend_out": BorrowRecord.STATUS_VALUE.LENDING_OUT,
            "out_of_time": BorrowRecord.STATUS_VALUE.OVERDUE,
            "returned": BorrowRecord.STATUS_VALUE.RETURNED,
            "banned": BorrowRecord.STATUS_VALUE.BACK_TO_SYS
        }
        let status = codeConverter[$('input[name="classStatusRadio"]:checked').val()];
        console.log(status);
        params.set('status', status);

        const url = new URL(window.location);
        url.search = params.toString();
        window.history.replaceState({}, '', url);

        reloadPage(1, LIMIT_DATA_NUMBER);
    });


    // 搜尋重設
    $('#reset_search_query').click(() => {
        // 取消 radio 勾選
        $('input[name="classStatusRadio"]:checked').prop('checked', false);

        // 更新 URL，刪掉 status 參數
        const url = new URL(window.location);
        url.searchParams.delete('contact');
        url.searchParams.delete('property');
        url.searchParams.delete('lendout_date');
        url.searchParams.delete('return_date');
        url.searchParams.delete('department');
        url.searchParams.delete('prepare_return');
        url.searchParams.delete('status');
        url.searchParams.set('page', 1); // 預設回第一頁
        window.history.replaceState({}, '', url);

        // 重新載入資料
        reloadPage(1, LIMIT_DATA_NUMBER);
    });



});

function adjustLimitNumber() {
    const width = window.innerWidth;

    if (width < 768) { // 小螢幕（手機）
        return 5;
    } else if (width > 1600) { // 超大螢幕
        return 10;
    } else { // 一般螢幕
        return 7;
    }
}


function getPropertyWithID(id, callback) {
    $.ajax({
        url: '/property/info/getWithBorrowID', // 替換為你的 API URL
        type: 'POST',
        data: {
            borrow_id: id,
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            // 假設 response 返回新的表格資料，根據需求去更新表格
            //console.log(response.data);
            callback(response.data);
        },
        error: function (error) {
            console.log('Error:', error);
            callback([]);
        }
    });
}

// 產生詳細按鈕
function genDetailButton(data) {
    $('#lending_status').empty();
    G_records.clear();
    $.each(data, function (index, item) {
        G_records[item.id] = new BorrowRecord(item);

        const filters = {
            [BorrowRecord.STATUS_VALUE.RETURNED]        : 'alert-secondary',
            [BorrowRecord.STATUS_VALUE.LENDING_OUT]     : 'alert-success',
            [BorrowRecord.STATUS_VALUE.OVERDUE]         : 'alert-warning',
            [BorrowRecord.STATUS_VALUE.WAIT_FOR_LEND]   : 'alert-primary',
            [BorrowRecord.STATUS_VALUE.BACK_TO_SYS]     : 'alert-danger',
        };

        // Generate modal and button
        let trig_btn = `<a href="${CONTROL_PANEL_URL}?id=${item.id}" class="text-dark">
        <i class="bi bi-three-dots"></i></a>`;

        // Generate table row
        let row = `
            <div class="card mb-2 ${filters[item.status]} text-dark">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-2">${item.filling_time}</div>
                        <div class="col-md-2">${item.borrow_date} ~ ${item.returned_date}</div>
                        <div class="col-md-2">${item.borrow_department}</div>
                        <div class="col-md-1">${item.borrow_person_name}</div>
                        <div class="col-md-2">${item.phone}</div>
                        <div class="col-md-2">${item.email}</div>
                        <div class="col-md-1">${trig_btn}</div>
                    </div>
                </div>
            </div>
        `;

        // Append the row to the table body
        $('#lending_status').append(row);
    });
}


function reloadPage(page = getPageFromWebURL(), limit = LIMIT_DATA_NUMBER) {
    let place = getLocationFromWebURL();
    let $location_selector = $('input[name="place"]');

    const params = new URLSearchParams(window.location.search);
    let contact = params.get('contact') || '';
    let property = params.get('property') || '';
    let lendout_date = params.get('lendout_date') || '';
    let return_date = params.get('return_date') || '';
    let department = params.get('department') || '';
    let prepare_return = params.get('prepare_return') || '';
    let status = params.get('status') || '';

    // 勾選 radio
    $location_selector.prop('checked', false);
    $location_selector.filter(`[value="${place}"]`).prop('checked', true);

    // 更新 URL
    updateURL(page, place);


    $('#borrow_list').empty();
    spinnerLoadingAction('show');
    $.ajax({
        type: 'GET',
        url: `/borrow/getData?page=${page}&limit=${limit}`,
        data: {
            location: place,
            contact,
            property,
            lendout_date,
            return_date,
            department,
            prepare_return,
            status
        },
        success: function (response) {
            if (response.success) {
                genDetailButton(response.data);
                spinnerLoadingAction('hide');
                generatePagination(page, limit, response.total);
            }
        }
    });
}

function getPageFromWebURL() {
    const params = new URLSearchParams(window.location.search);
    const page = parseInt(params.get('page'));
    return isNaN(page) ? 1 : page; // 如果沒有 page，就預設 1
}

function getLocationFromWebURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('location') || 'all'; // 預設空字串
}

function updateURL(page, location) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    if (location) url.searchParams.set('location', location);
    window.history.replaceState({}, '', url); // 不刷新頁面只更新 URL
}

function generatePagination(currentPage, limit, total, location) {
    const totalPages = Math.ceil(total / limit);
    let paginationHTML = '';

    let lower = total === 0 ? 0 : (currentPage - 1) * limit + 1;
    let upper = total === 0 ? 0 : Math.min(currentPage * limit, total);

    let record_text = `目前 ${lower}~${upper} 筆 , 總共 ${total} 筆 , ${total === 0 ? 0 : currentPage}/${totalPages}頁`;
    $('#totalRecords').text(record_text);


    // Previous 按鈕
    paginationHTML += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="" onclick="reloadPage(${currentPage - 1}, ${limit})"> 上一頁 </a>
        </li>
    `;

    // 計算頁碼區間
    let startPage = Math.max(1, currentPage - 1);
    let endPage = Math.min(totalPages, currentPage + 1);
    if (currentPage === 1) endPage = Math.min(3, totalPages);
    if (currentPage === totalPages) startPage = Math.max(totalPages - 2, 1);

    // 第一頁
    if (startPage > 1) {
        paginationHTML += `
            <li class="page-item"><a class="page-link" href="" onclick="reloadPage(1, ${limit})">1</a></li>
            <li class="page-item disabled"><a class="page-link">...</a></li>
        `;
    }

    // 中間頁碼
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="" onclick="reloadPage(${i}, ${limit})">${i}</a>
            </li>
        `;
    }

    // 最後一頁
    if (endPage < totalPages) {
        paginationHTML += `
            <li class="page-item disabled"><a class="page-link">...</a></li>
            <li class="page-item"><a class="page-link" href="" onclick="reloadPage(${totalPages}, ${limit})">${totalPages}</a></li>
        `;
    }

    // Next 按鈕
    paginationHTML += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="" onclick="reloadPage(${currentPage + 1}, ${limit})"> 下一頁 </a>
        </li>
    `;

    $('#pagination').html(paginationHTML);
}


// Animation
function spinnerLoadingAction(action) {
    if (action == 'show') {
        $('#loading').removeClass('visually-hidden');
    }
    else {
        $('#loading').addClass('visually-hidden');
    }
}
