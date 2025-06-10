$(document).ready(function () {

    // 地點查詢
    $('input[name="place"]').on('change', reloadPage);
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
    $('#reset_search_query').click(reloadPage);


    // 將資料帶入Modal邏輯
    $(document).on('click', '.btn-bring-data', function () {
        let borrowListId = $(this).data('combine').split('_')[0];
        getPropertyWithID(borrowListId, function (lending_property) {
            bringLendingDataIntoModal(lending_property);
            bringSADataIntoModal(borrowListId);
        });
    });

    // Modal 開始借用
    $('#start-lending').on('click', () => {
        manuplateButtonStatusChange($('#sa_manuplate').val());
    });


    $('#scan_list').on('input', function () {
        const inputVal = $(this).val();
        if (inputVal.length >= 8) {
            let itemArray = [];

            // 管理員指令碼輸入區
            if (cheatCodeAction(inputVal)) {
                $('#scan_list').val('');
                return;
            }

            // 從 modal 中的 tbody 名稱為 'borrow_list' 的每個 tr 中提取第一個 td 的值
            $('#borrow_list tr').each(function () {
                const firstTdValue = $(this).find('td').eq(0).text().trim(); // 第一個 <td>
                const statusTdValue = $(this).find('td').eq(6).text().trim(); // 第7個 <td>（索引從 0 開始）

                if (statusTdValue !== '退回系統')
                    itemArray.push(firstTdValue);

            });

            //console.log(itemArray);

            // 檢查 input 的值是否存在於 itemArray 中
            if (itemArray.includes(inputVal)) {
                // 如果存在，找到對應的 tr 並加上 class 'table-success'
                $('#borrow_list tr').each(function () {
                    const firstTdValue = $(this).find('td:first').text().trim();
                    if (firstTdValue === inputVal) {
                        $(this).addClass('table-success');
                        alert("掃描成功");
                    }
                });
            } else {
                alert("清單無此物品或此物已退回系統");
            }
            $('#scan_list').val('');
        }
    });

    $('#save-data').on('click', function (e) {
        // Validation check
        let isValid = function () {
            let valid = true; // 預設為通過驗證

            if (!$('#sa_manuplate').val()) {
                $('#check_sa_manuplate').addClass('invalid-feedback').text('要填');
                valid = false;
            } else {
                $('#check_sa_manuplate').text('').removeClass('invalid-feedback').addClass('valid-feedback');
            }

            if ($('#sa_manuplate').val() == 'borrow') {
                if (!$('#sa_lending_person_name').val()) {
                    $('#check_sa_lending_person_name').addClass('invalid-feedback').text('要選');
                    valid = false;
                }
                else {
                    $('#check_sa_lending_person_name').text('').removeClass('invalid-feedback').addClass('valid-feedback');
                }
            }
            else if ($('#sa_manuplate').val() == 'return') {
                if (!$('#sa_return_person_name').val()) {
                    $('#check_sa_return_person_name').addClass('invalid-feedback').text('要選');
                    valid = false;
                }
                else {
                    $('#check_sa_return_person_name').text('').removeClass('invalid-feedback').addClass('valid-feedback');
                }
            }
            return valid;
        };

        // 這裡要執行函式，判斷結果
        $('#modal-form').addClass('was-validated');
        if (!isValid()) {
            e.preventDefault()
            e.stopPropagation()
            return false;
        }

        // 隱藏相關區塊，並跳出確認視窗
        manuplateButtonStatusChange('double_check');

        // 提示文字檢查
        $('#borrow_list tr').each(function () {
            const changeBadge = $(this).find('td').eq(6); // Badge欄位
            const borrowStatus = $('#sa_manuplate').val();

            // 清空原內容


            // 建立 badge DOM 元素
            let $badge = $('<span>').addClass('badge');
            let $icon = $('<i>');

            if (borrowStatus == 'borrow') {
                if ($(this).hasClass('table-success')) {
                    changeBadge.empty();
                    $badge.addClass('bg-success').text(' 即將借出');
                    $icon.addClass('bi bi-arrow-up-right-circle');
                }
                else {
                    changeBadge.empty();
                    $badge.addClass('bg-danger').text(' 即將退回系統');
                    $icon.addClass('bi bi-rewind-circle');
                }
            }
            else if (borrowStatus === 'return') {
                if ($(this).hasClass('table-success')) {
                    changeBadge.empty();
                    $badge.addClass('bg-secondary').text(' 即將歸還');
                    $icon.addClass('bi bi-arrow-down-left-circle');
                }
                else if ($(this).hasClass('table-secondary')) {
                    return true;
                }
                else {
                    changeBadge.empty();
                    $badge.addClass('bg-warning text-dark').text(' 尚待歸還');
                    $icon.addClass('bi bi-exclamation-circle');
                }
            }

            // 把 icon 插入到 badge 前面
            $badge.prepend($icon);

            // 加入到欄位中
            changeBadge.append($badge);
        });

    });

    $('#double-check-save').on('click', function () {
        // 找到按鈕所在的 Modal，然後向上或向下找到相關的子區塊
        const modal = $('#modal'); // 找到 modal

        // 提取出借用清單ssid
        let borrow = [];
        let no_borrow = [];
        $('#borrow_list tr').each(function () {
            const firstTdValue = $(this).find('td:first').text().trim();
            // 檢查 tr 是否具有 class 'table-success'
            if ($(this).hasClass('table-success')) {
                borrow.push(firstTdValue); // 有 table-success class
            } else {
                no_borrow.push(firstTdValue); // 沒有 table-success class
            }
        });


        $('#borrow_id').prop('disabled', false);
        let pack_data = {
            sa_manuplate: $('#sa_manuplate').val(),
            sa_lending_person_name: $('#sa_lending_person_name').val(),
            sa_lending_date: $('#sa_lending_date').val(),
            sa_deposit_take: $('#sa_deposit_take').val(),
            sa_id_take: $('#sa_id_take').val(),
            sa_id_deposit_box_number: $('#sa_id_deposit_box_number').val(),

            sa_return_person_name: $('#sa_return_person_name').val(),
            sa_returned_date: $('#sa_returned_date').val(),
            sa_deposit_returned: $('#sa_deposit_returned').val(),
            sa_id_returned: $('#sa_id_returned').val(),

            sa_remark: $('#sa_remark').val(),

            borrow_id: $('#borrow_id').val(),
            borrow_items: borrow,
            no_borrow_items: no_borrow
        };
        //console.log(pack_data);

        //$('input, select, textarea').prop('disabled', true);
        if (true) {
            // Ajax Send to backend

            $.ajax({
                type: 'POST',
                url: '/borrow/item/final',
                data: {
                    pack_data: JSON.stringify(pack_data),
                    _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
                },
                success: function (response) {
                    //console.log(response);
                    if (response.success) {
                        sessionStorage.setItem('place', $('input[name="place"]:checked').val());
                        alert('完成');
                        manuplateButtonStatusChange('hidden');
                        $("#modal").modal("hide");
                        reloadPage();
                    }
                    else {
                        alert(response.error);
                    }
                },
                error: function (error) {
                    console.log(error);
                }
            });
        }
        else {
            return;
        }
    });

    $('#double-check-close').on('click', function (param) {
        // 返回填寫到一半的狀態
        manuplateButtonStatusChange('callback');
        $('#borrow_list tr').each(function () {
            const changeBadge = $(this).find('td').eq(6); // Badge欄位
            const changeBadgeText = $(this).find('td').eq(6).text().trim(); // Badge欄位
            const borrowStatus = $('#sa_manuplate').val();
            // 清空原內容
            // 建立 badge DOM 元素
            let $badge = $('<span>').addClass('badge');
            let $icon = $('<i>');

            if (changeBadgeText == '即將借出') {
                changeBadge.empty();
                $badge.addClass('bg-primary').text(' 待借出');
                $icon.addClass('bi bi-exclamation-circle-fill');
            }
            else if (changeBadgeText == '即將退回系統') {
                changeBadge.empty();
                $badge.addClass('bg-danger').text(' 退回系統');
                $icon.addClass('bi bi-backspace');
            }
            else if (changeBadgeText == '即將歸還' || changeBadgeText == '尚待歸還') {
                changeBadge.empty();
                $badge.addClass('bg-success').text(' 外借中');
                $icon.addClass('bi bi-box-arrow-right');
            }
            // 把 icon 插入到 badge 前面
            $badge.prepend($icon);

            // 加入到欄位中
            changeBadge.append($badge);
        });
    });

    $('#modal-close').on('click', function () {
        $('#borrow_list').empty();
        $('#borrow_id').val();
        $('#sa_manuplate').val('');
        $('#sa_lending_person_name').val();
        $('#sa_lending_date').val();
        $('#sa_deposit_take').val();
        $('#sa_id_take').val();
        $('#sa_id_deposit_box_number').val();
        $('#sa_return_person_name').val();
        $('#sa_returned_date').val();
        $('#sa_deposit_returned').val();
        $('#sa_id_returned').val();
        $('#sa_remark').val();

        // 全部關閉
        manuplateButtonStatusChange('hidden');
    });

    $('input[name="btnradio"]').on('change', function () {
        reloadPage($(this).val());
    });

});

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

// Modal資料帶入
function bringLendingDataIntoModal(lending_property) {
    //console.log(combine_data);
    //console.log(lending_property);
    let constraint_seq = new Set();
    $('#borrow_list').empty();
    let formattedInfo = '';
    $.each(lending_property, function (index, item) {
        let unable_borrow = "table-secondary";
        constraint_seq.add(item.status);
        // Status為 Borrow之Column 非財產現在借用狀態
        if (item.status == 1) {
            item.status = '<span class="badge bg-success"><i class="bi bi-box-arrow-right"></i> 外借中</span>';
            unable_borrow = "";
        }
        else if (item.status == 2) {
            item.status = '<span class="badge bg-primary"><i class="bi bi-exclamation-circle-fill"></i> 待借出</span>';
            unable_borrow = "";
        }
        else if (item.status == 3) { // Status 3 代表已歸還
            item.status = '<span class="badge bg-secondary"><i class="bi bi-arrow-return-left"></i> 已歸還</span>';
            unable_borrow = "";
        }
        else { // Status 0 不可以被借
            item.status = '<span class="badge bg-danger"><i class="bi bi-backspace"></i> 退回系統</span>';
        }
        let col =
            `<tr class="${unable_borrow}">
                <td style="width: 10%">${item.ssid}</td>
                <td style="width: 15%">${item.name}</td>
                <td style="width: 10%">${item.second_name == null ? '' : item.second_name}</td>
                <td style="width: 10%">${item.class}</td>
                <td style="width: 20%">${item.format}</td>
                <td style="width: 15%">${item.remark == null ? '' : item.remark}</td>
                <td style="width: 5%">${item.status}</td>
                <td style="width: 15%"><img src="./storage/propertyImgs/${item.img_url}" style="width: 100px; height: auto;"></td>
            </tr>`;

        formattedInfo += col;
    });
    manuplateConstraint(constraint_seq);
    $('#borrow_list').append(formattedInfo);
}

function bringSADataIntoModal(id) {
    $.ajax({
        url: '/borrow/getData/single', // 替換為你的 API URL
        type: 'POST',
        data: {
            id: id,
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            //console.log(response.data);
            let single_info = response.data;

            function setInputValue(selector, value) {
                $(selector).val(value);
            }

            $('#borrow_id').val(id);
            setInputValue('#sa_lending_person_name', single_info.sa_lending_person_name);
            setInputValue('#sa_lending_date', single_info.sa_lending_date);
            setInputValue('#sa_deposit_take', single_info.sa_deposit_take);
            setInputValue('#sa_id_take', single_info.sa_id_take);
            setInputValue('#sa_id_deposit_box_number', single_info.sa_id_deposit_box_number);
            setInputValue('#sa_return_person_name', single_info.sa_return_person_name);
            setInputValue('#sa_returned_date', single_info.sa_returned_date);
            setInputValue('#sa_deposit_returned', single_info.sa_deposit_returned);
            setInputValue('#sa_id_returned', single_info.sa_id_returned);
            setInputValue('#sa_remark', single_info.sa_remark);
        },
        error: function (error) {
            console.log('Error:', error);
        }
    });


}

function bringChargePersonListIntoModal() {
    // 發送 AJAX 
    $.ajax({
        url: '/show-user-name', // 替換為你的 API URL
        type: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            // 假設 response 返回新的表格資料，根據需求去更新表格
            //console.log(response.data);
            let personList = response.data;
            const lending_person_list = $("#sa_lending_person_name");
            const return_person_list = $("#sa_return_person_name");
            lending_person_list.empty(); // 清空舊選項
            return_person_list.empty();

            lending_person_list.append(`<option selected disabled value="">請選擇承辦人</option>`);
            return_person_list.append(`<option selected disabled value="">請選擇承辦人</option>`);
            // 動態添加新選項
            $.each(personList, function (index, person) {
                lending_person_list.append(`<option value="${person.name}">${person.name}</option>`);
                return_person_list.append(`<option value="${person.name}">${person.name}</option>`);
            });
        },
        error: function (error) {
            console.log('Error:', error);
        }
    });
}

// Modal 填寫權限限制
function manuplateConstraint(constraint) {
    /* 
    Status:{
        0: 退回系統,
        1: 外借中,
        2: 待借出,
        3: 已歸還          
    }
    */
    let control_button = $('#sa_manuplate');
    $('#lending_out').prop('hidden', true);
    $('#return_in').prop('hidden', true);
    $('#start-lending').prop('disabled', false);
    manuplateButtonStatusChange('hidden');
    // 如果有未歸還，設為歸還
    if (constraint.has(1)) {
        control_button.val('return');
    }
    // 如果有器材還沒借出，設為借出
    else if (constraint.has(2)) {
        control_button.val('borrow');
    }
    // 其餘設為不讓借用
    else {
        control_button.val('');
        $('#start-lending').prop('disabled', true);
    }
}

function manuplateButtonStatusChange(mode) {
    switch (mode) {
        case "borrow":
            // 借用區 取消 disabled
            $('#lending_out').prop('hidden', false);
            $('#area_sa_id_deposit_box_number').prop('hidden', false);
            $('#return_in').prop('hidden', true);

            $('#sa_id_deposit_box_number').prop('disabled', false);
            $('#scan_list').prop('disabled', false);
            $('#sa_remark').prop('disabled', false);
            $('#save-data').prop('disabled', false);
            break;
        case "return":
            // 歸還區 取消 disabled
            $('#lending_out').prop('hidden', true);
            $('#area_sa_id_deposit_box_number').prop('hidden', false);
            $('#return_in').prop('hidden', false);

            $('#sa_id_deposit_box_number').prop('disabled', true);
            $('#scan_list').prop('disabled', false);
            $('#sa_remark').prop('disabled', false);
            $('#save-data').prop('disabled', false);
            break;
        case 'hidden':
            // 全部隱藏
            $('#lending_out').prop('hidden', true);
            $('#return_in').prop('hidden', true);
            $('#area_sa_id_deposit_box_number').prop('hidden', true);
            $('#area-first-check-scan').prop('hidden', false);
            $('#area-first-check-remark').prop('hidden', false);
            $('#area-first-check-button').prop('hidden', false);

            $('#area-double-check-confirm-text').prop('hidden', true);
            $('#area-double-check').prop('hidden', true);
            // 掃描、備註、儲存按鈕關閉
            $('#area_sa_id_deposit_box_number').prop('disabled', true);
            $('#scan_list').prop('disabled', true);
            $('#sa_remark').prop('disabled', true);
            $('#save-data').prop('disabled', true);
            break;
        case 'double_check':
            $('#lending_out').prop('hidden', true);
            $('#return_in').prop('hidden', true);
            $('#area_sa_id_deposit_box_number').prop('hidden', true);
            $('#area-first-check-scan').prop('hidden', true);
            $('#area-first-check-remark').prop('hidden', true);
            $('#area-first-check-button').prop('hidden', true);

            $('#area-double-check-confirm-text').prop('hidden', false);
            $('#area-double-check').prop('hidden', false);
            // 掃描、備註、儲存按鈕關閉
            $('#area_sa_id_deposit_box_number').prop('disabled', true);
            $('#scan_list').prop('disabled', true);
            $('#sa_remark').prop('disabled', true);
            $('#save-data').prop('disabled', true);
            break;
        case 'callback':
            if($('#sa_manuplate').val() == 'borrow')
                manuplateButtonStatusChange('borrow');
            else
                manuplateButtonStatusChange('return');
            $('#area_sa_id_deposit_box_number').prop('hidden', false);
            $('#area-first-check-scan').prop('hidden', false);
            $('#area-first-check-remark').prop('hidden', false);
            $('#area-first-check-button').prop('hidden', false);

            $('#area-double-check-confirm-text').prop('hidden', true);
            $('#area-double-check').prop('hidden', true);
            // 掃描、備註、儲存按鈕關閉
            $('#area_sa_id_deposit_box_number').prop('disabled', false);
            $('#scan_list').prop('disabled', false);
            $('#sa_remark').prop('disabled', false);
            $('#save-data').prop('disabled', false);
            break;
        case 'MANUAL_TEST':
            // 全部顯示
            $('#lending_out').prop('hidden', false);
            $('#return_in').prop('hidden', false);
            $('#area_sa_id_deposit_box_number').prop('hidden', false);

            $('#area_sa_id_deposit_box_number').prop('disabled', false);
            $('#scan_list').prop('disabled', false);
            $('#sa_remark').prop('disabled', false);
            $('#save-data').prop('disabled', false);
            break;

    }
    return mode;
}

// 產生詳細按鈕
function genDetailButton(data, statusFiltering = 'no') {
    $('#lending_status').empty();

    $.each(data, function (index, item) {

        let lending_status = item.status;
        let expired_return = new Date(item.returned_date);
        expired_return.setHours(0, 0, 0, 0);

        const filters = {
            returned: 'table-secondary',
            lend_out: 'table-success',
            out_of_time: 'table-warning',
            waiting: 'table-primary',
            banned: 'table-danger',
        };

        const today = getTodayDate();
        const statusClasses = ['table-danger', 'table-success', 'table-primary', 'table-secondary'];
        lending_status = statusClasses[lending_status] || '';


        // 逾期處理
        if (lending_status === 'table-success' && expired_return < today) {
            lending_status = 'table-warning';
        }

        // 若 `statusFiltering` 需要篩選，且當前 `lending_status` 不匹配，則跳過
        if (filters[statusFiltering] && lending_status !== filters[statusFiltering]) {
            return;
        }

        // Generate modal and button
        let trig_btn = `<button type="button" class="btn btn-info btn-bring-data" data-bs-toggle="modal" data-bs-target="#modal" data-combine="${item.id}_combine">
        <i class="bi bi-clipboard-data"></i> 借用資訊</button>`;

        // Generate table row
        let row = `
            <tr class="${lending_status}">
                <td>${item.filling_time}</td>
                <td>${item.borrow_department}</td>
                <td style="display: none">${item.borrow_date}</td>
                <td>${item.returned_date}</td>
                <td>${item.borrow_person_name}</td>
                <td>${item.phone}</td>
                <td>${item.email}</td>
                <td>${trig_btn}</td>
            </tr>
        `;

        // Append the row to the table body
        $('#lending_status').append(row);
    });
}


function reloadPage(statusFiltering = 'no') {
    $('#borrow_list').empty();
    $('#lending_table').attr('hidden', false);
    spinnerLoadingAction('show');
    bringChargePersonListIntoModal();
    $.ajax({
        type: 'POST',
        url: '/borrow/getData/',
        data: {
            location: $('input[name="place"]:checked').val(),
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            //console.log(response);
            if (response.success) {
                genDetailButton(response.data, statusFiltering);
                spinnerLoadingAction('hide');
            }
        },
        error: function (error) {
            console.log(error);
        }
    });
}

function getTodayDate() {
    const now = new Date();
    const localDate = new Date(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Taipei' }).format(now));
    return localDate;
}

// Admin Control Code Action
function cheatCodeAction(cheat) {
    const actions = {
        SHOWLIST: () => manuplateButtonStatusChange('MANUAL_TEST'),
        HIDELIST: () => manuplateButtonStatusChange($('#sa_manuplate').val()),
        _MANUAL_: () => {
            manuplateButtonStatusChange('MANUAL_TEST');
            $('#area_borrow_id').prop('hidden', false);
            $('#area_sa_manuplate').prop('hidden', false);
        },
        UNLOCKID: () => {
            $('#borrow_id').prop('disabled', false);
        },
        __AUTO__: () => {
            manuplateButtonStatusChange($('#sa_manuplate').val());
            $('#borrow_id').prop('disabled', false);
            $('#area_borrow_id').prop('hidden', true);
            $('#area_sa_manuplate').prop('hidden', true);
        }
    };

    if (actions[cheat]) {
        actions[cheat]();
        return true;
    }
    return false;
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
