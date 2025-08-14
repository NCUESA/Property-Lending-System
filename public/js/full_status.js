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
    $('input[name="place"]').on('change', () => {
        const newLocation = $(this).val();
        const currentPage = 1; // 選 radio 時從第一頁開始
        updateURL(currentPage, newLocation);
        reloadPage(page = 1, limit = LIMIT_DATA_NUMBER);
    });


    // 搜尋
    $('#search').submit(function (e) {
        e.preventDefault();
        spinnerLoadingAction('show');
        $.ajax({
            type: 'POST',
            url: '/borrow/getData/condition',
            data: {
                contact: $('#search_contact').val(),
                property: $('#search_property').val(),
                lendout_date: $('#search_lendout').val(),
                return_date: $('#search_return').val(),
                department: $('#search_department').val(),
                prepare_return: $('#search_prepare_return').val(),
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    genDetailButton(response.data);
                    spinnerLoadingAction('hide');
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    // 搜尋重設
    $('#reset_search_query').click(() => {
        reloadPage(page = 1, limit = LIMIT_DATA_NUMBER);
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
            [BorrowRecord.STATUS_VALUE.RETURNED]: 'table-secondary',
            [BorrowRecord.STATUS_VALUE.LENDING_OUT]: 'table-success',
            [BorrowRecord.STATUS_VALUE.OVERDUE]: 'table-warning',
            [BorrowRecord.STATUS_VALUE.WAIT_FOR_LEND]: 'table-primary',
            [BorrowRecord.STATUS_VALUE.BACK_TO_SYS]: 'table-danger',
        };

        // Generate modal and button
        let trig_btn = `<a href="${CONTROL_PANEL_URL}?id=${item.id}" type="button" class="btn btn-info">
        <i class="bi bi-clipboard-data"></i> 借用資訊</a>`;

        // Generate table row
        let row = `
            <tr class="${filters[item.status]}">
                <td>${item.filling_time}</td>
                <td>${item.borrow_date}~${item.returned_date}</td>
                <td>${item.borrow_department}</td>
                <td>${item.borrow_person_name}</td>
                <td><i class="bi bi-telephone"></i> ${item.phone}</td>
                <td><i class="bi bi-envelope"></i> ${item.email}</td>
                <td>${trig_btn}</td>
            </tr>
        `;

        // Append the row to the table body
        $('#lending_status').append(row);
    });
}


function reloadPage(page = getPageFromWebURL(), limit = LIMIT_DATA_NUMBER) {
    let place = getLocationFromWebURL();
    let $location_selector = $('input[name="place"]');

    // 勾選 radio
    $location_selector.prop('checked', false);
    $location_selector.filter(`[value="${place}"]`).prop('checked', true);

    // 更新 URL
    updateURL(page, place);


    $('#borrow_list').empty();
    $('#lending_table').attr('hidden', false);
    spinnerLoadingAction('show');
    $.ajax({
        type: 'GET',
        url: `/borrow/getData?page=${page}&limit=${limit}`,
        data: {
            location: place,
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
    return params.get('location') || ''; // 預設空字串
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
