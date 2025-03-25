$(document).ready(function () {

    // 地點查詢
    $('#place').on('change', function () {
        reloadPage();
    });
    // 搜尋
    $('#search').submit(function (e) {
        e.preventDefault();
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
                    startFillingForm();
                    genDataButton(response.data);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $('#reset_search_query').click(function () {
        $('#place').val('');
        reloadPage();
    });


    // 將資料帶入Modal邏輯
    $(document).on('click', '.btn-bring-data', function () {
        let combine_data = fullLendingData[$(this).data('combine')];
        let borrowListId = combine_data['borrow_list_id'];
        getPropertyWithID(borrowListId, function (lending_property) {
            bringDataIntoModal(combine_data, lending_property);
        });
    });

    // Modal 開始借用
    $('#start-lending').on('click', function () {
        $('#sa_manuplate').prop('disabled', false);
        $('#scan_list').prop('disabled', false);
        $('#sa_remark').prop('disabled', false);
    });

    $('#sa_manuplate').on('change', function () {
        switch ($(this).val()) {
            case "borrow":
                // 借用區 取消 disabled
                $('#sa_lending_person_name').prop('disabled', false);
                $('#sa_lending_date').prop('disabled', false);
                $('#sa_deposit_take').prop('disabled', false);
                $('#sa_id_take').prop('disabled', false);
                $('#sa_id_deposit_box_number').prop('disabled', false);

                // 歸還區 disabled
                $('#sa_return_person_name').prop('disabled', true);
                $('#sa_returned_date').prop('disabled', true);
                $('#sa_deposit_returned').prop('disabled', true);
                $('#sa_id_returned').prop('disabled', true);

                break;
            case "return":
                // 借用區 disabled
                $('#sa_lending_person_name').prop('disabled', true);
                $('#sa_lending_date').prop('disabled', true);
                $('#sa_deposit_take').prop('disabled', true);
                $('#sa_id_take').prop('disabled', true);
                $('#sa_id_deposit_box_number').prop('disabled', true);

                // 歸還區 取消 disabled
                $('#sa_return_person_name').prop('disabled', false);
                $('#sa_returned_date').prop('disabled', false);
                $('#sa_deposit_returned').prop('disabled', false);
                $('#sa_id_returned').prop('disabled', false);
                break;
        }
    });

    $('#scan_list').on('input', function () {
        const inputVal = $(this).val();

        if (inputVal.length >= 8) {
            let itemArray = [];


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
        const borrowListId = $(this).data('borrowListId');

        // The logic of precheck
        let isValid = function () {
            let valid = true; // 預設為通過驗證

            if (!$('#sa_manuplate').val()) {
                $('#check_sa_manuplate').addClass('invalid-feedback').text('要填');
                valid = false;
            } else {
                $('#check_sa_manuplate').text('').removeClass('invalid-feedback').addClass('valid-feedback');
            }
            /*
            if (!$('#sa_lending_person_name').val()) {
                $('#check_sa_lending_person_name').addClass('invalid-feedback').text('要填');
                valid = false;
            } else {
                $('#check_sa_lending_person_name').text('').removeClass('invalid-feedback').addClass('valid-feedback');
            }

            if (!$('#sa_return_person_name').val()) {
                $('#check_sa_return_person_name').addClass('invalid-feedback').text('要填');
                valid = false;
            } else {
                $('#check_sa_return_person_name').text('').removeClass('invalid-feedback').addClass('valid-feedback');
            }*/

            return valid;
        };

        // 這裡要執行函式，判斷結果
        $('#modal-form').addClass('was-validated');
        if (!isValid()) {

            e.preventDefault()
            e.stopPropagation()
            return false;
        }



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

        let borrow_or_return = $('#sa_manuplate').val() == 'borrow' ? '借' : $('#sa_manuplate').val() == 'return' ? '還' : '';

        let confirm_string = '值勤人員請確認\n已' + borrow_or_return + '物品：';
        borrow.forEach(br_item => {
            confirm_string += br_item + ' ';
        });
        confirm_string += '\n未' + borrow_or_return + '物品：';
        no_borrow.forEach(br_item => {
            confirm_string += br_item + ' ';
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
        console.log(pack_data);

        $('input, select, textarea').prop('disabled', true);
        if (confirm(confirm_string) == true) {
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
                        sessionStorage.setItem('place', $('#place').val());
                        alert('完成');
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
        return;
    });

    $('#modal-close').on('click', function () {
        $('#borrow_list').empty();
        $('#borrow_id').val();
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
        $('#sa_lending_person_name').prop('disabled', true);
        $('#sa_lending_date').prop('disabled', true);
        $('#sa_deposit_take').prop('disabled', true);
        $('#sa_id_take').prop('disabled', true);
        $('#sa_id_deposit_box_number').prop('disabled', true);
        $('#sa_return_person_name').prop('disabled', true);
        $('#sa_returned_date').prop('disabled', true);
        $('#sa_deposit_returned').prop('disabled', true);
        $('#sa_id_returned').prop('disabled', true);
        $('#sa_manuplate').prop('disabled', true);
        $('#scan_list').prop('disabled', true);
        $('#sa_remark').prop('disabled', true);
    });

    $('input[name="btnradio"]').on('change', function () {
        reloadPage($(this).val());
    });

});

var fullLendingData = {};
// 完成勿動
function startFillingForm() {
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
            updateFormWithPersonList(response.data);
        },
        error: function (error) {
            console.log('Error:', error);
        }
    });
}

// 完成勿動
function updateFormWithPersonList(personList) {
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
}

function getPropertyWithID(id, callback) {
    $.ajax({
        url: '/get-property-with-borrow-id', // 替換為你的 API URL
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

function bringDataIntoModal(combine_data, lending_property) {
    //console.log(combine_data);
    //console.log(lending_property);

    $('#borrow_list').empty();
    let formattedInfo = '';
    $.each(lending_property, function (index, item) {
        let unable_borrow = "table-secondary";
        // Status為 Borrow之Column 非財產現在借用狀態
        if (item.status == 1) {
            item.status = '外借中';
            unable_borrow = "";
        }
        else if (item.status == 2) {
            item.status = '待借出';
            unable_borrow = "";
        }
        else if (item.status == 3) { // Status 3 代表已歸還
            item.status = '已歸還';
            unable_borrow = "";
        }
        else { // Status 0 不可以被借
            item.status = '退回系統';
        }
        let col =
            `<tr class="${unable_borrow}">
                <td>${item.ssid}</td>
                <td>${item.name}</td>
                <td>${item.second_name}</td>
                <td>${item.class}</td>
                <td>${item.format}</td>
                <td>${item.remark}</td>
                <td>${item.status}</td>
                <td><img src="./storage/propertyImgs/${item.img_url}" style="width: 100px; height: auto;"></td>
            </tr>`;

        formattedInfo += col;
    });
    $('#borrow_list').append(formattedInfo);

    $('#borrow_id').val(combine_data['borrow_list_id']);
    $('#sa_lending_person_name').val(combine_data['sa_lending_person_name']);
    $('#sa_lending_date').val(combine_data['sa_lending_date']);
    $('#sa_deposit_take').val(combine_data['sa_deposit_take']);
    $('#sa_id_take').val(combine_data['sa_id_take']);
    $('#sa_id_deposit_box_number').val(combine_data['sa_id_deposit_box_number']);
    $('#sa_return_person_name').val(combine_data['sa_return_person_name']);
    $('#sa_returned_date').val(combine_data['sa_returned_date']);
    $('#sa_deposit_returned').val(combine_data['sa_deposit_returned']);
    $('#sa_id_returned').val(combine_data['sa_id_returned']);
    $('#sa_remark').val(combine_data['sa_remark']);
}

// 完成勿動
function genDataButton(data, statusFiltering = 'no') {
    $('#lending_status').empty();
    fullLendingData = {};

    $.each(data, function (index, item) {
        let sa_lending_person_name = item.sa_lending_person_name == null ? '' : item.sa_lending_person_name;
        let sa_lending_date = item.sa_lending_date == null ? '' : item.sa_lending_date;

        let sa_deposit_take = item.sa_deposit_take == 0 ? '❌' : '✅';
        let sa_id_take = item.sa_id_take == 0 ? '❌' : '✅';
        let sa_id_returned = item.sa_id_returned == 0 ? '❌' : '✅';
        let sa_deposit_returned = item.sa_deposit_returned == 0 ? '❌' : '✅';

        let sa_id_deposit_box_number = item.sa_id_deposit_box_number == null ? '' : item.sa_id_deposit_box_number;
        let sa_return_person_name = item.sa_return_person_name == null ? '' : item.sa_return_person_name;
        let sa_returned_date = item.sa_returned_date == null ? '' : item.sa_returned_date;
        let sa_remark = item.sa_remark == null ? '' : item.sa_remark;


        let lending_status = item.status;
        let expired_return = new Date(item.returned_date);
        expired_return.setHours(0, 0, 0, 0);


        switch (lending_status) {
            case 0:     // Dispatch
                lending_status = 'table-danger';
                break;
            case 1:
                lending_status = 'table-success';   // Lendout
                if (expired_return <= getTodayDate()) {
                    lending_status = 'table-warning'
                }
                break;
            case 2:     //Waiting
                lending_status = 'table-primary';
                break;
            case 3:     //Return
                lending_status = 'table-secondary';
                break;
        }

        switch (statusFiltering) {
            case 'returned':
                if (lending_status !== 'table-secondary') {
                    return;
                }
                break;
            case 'lend_out':
                if (lending_status !== 'table-success') {
                    return;
                }
                break;
            case 'out_of_time':
                if (lending_status !== 'table-warning') {
                    return;
                }
                break;
            case 'waiting':
                if (lending_status !== 'table-primary') {
                    return;
                }
                break;
            case 'banned':
                if (lending_status !== 'table-danger') {
                    return;
                }
                break;
        }

        let combine_data = {
            borrow_list_id: item.id,
            sa_lending_person_name: item.sa_lending_person_name,
            sa_lending_date: item.sa_lending_date,
            sa_deposit_take: item.sa_deposit_take,
            sa_id_take: item.sa_id_take,
            sa_id_returned: item.sa_id_returned,
            sa_deposit_returned: item.sa_deposit_returned,

            sa_id_deposit_box_number: item.sa_id_deposit_box_number,
            sa_return_person_name: item.sa_return_person_name,
            sa_returned_date: item.sa_returned_date,
            sa_remark: item.sa_remark
        };


        fullLendingData[item.id + '_combine'] = combine_data;
        // Lending Property Info


        //let bg_color = item.borrow_place == '進德' ? 'table-info' : 'table-secondary';

        // Generate modal and button
        let trig_btn = `<button type="button" class="btn btn-info btn-bring-data" data-bs-toggle="modal" data-bs-target="#modal" data-combine="${item.id}_combine">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clipboard-data-fill" viewBox="0 0 16 16">
            <path d="M6.5 0A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0zm3 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5z"/>
            <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1A2.5 2.5 0 0 1 9.5 5h-3A2.5 2.5 0 0 1 4 2.5zM10 8a1 1 0 1 1 2 0v5a1 1 0 1 1-2 0zm-6 4a1 1 0 1 1 2 0v1a1 1 0 1 1-2 0zm4-3a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0v-3a1 1 0 0 1 1-1"/>
        </svg>借用資訊</button>`;

        // Generate table row
        let row = `
            <tr class="${lending_status}">
                <td style="display: none">${item.id}</td>
                <td style="display: none">${sa_lending_person_name}</td>
                <td style="display: none">${sa_lending_date}</td>
                <td style="display: none">${sa_id_take}</td>
                <td style="display: none">${sa_deposit_take}</td>
                <td style="display: none">${sa_id_deposit_box_number}</td>
                <td style="display: none">${sa_return_person_name}</td>
                <td style="display: none">${sa_returned_date}</td>
                <td style="display: none">${sa_id_returned}</td>
                <td style="display: none">${sa_deposit_returned}</td>
                <td style="display: none">${sa_remark}</td>
                <td>${item.filling_time}</td>
                <td>${item.email}</td>
                <td>${item.borrow_department}</td>
                <td>${item.borrow_person_name}</td>
                <td>${item.phone}</td>
                <td>${item.borrow_date}</td>
                <td>${item.returned_date}</td>
                <td>${trig_btn}</td>
            </tr>
        `;

        // Append the row to the table body
        $('#lending_status').append(row);
    });
}

function reloadPage(statusFiltering = 'no') {
    $('#borrow_list').empty();
    $.ajax({
        type: 'POST',
        url: '/borrow/getData/',
        data: {
            location: $('#place').val(),
            _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
        },
        success: function (response) {
            //console.log(response);
            if (response.success) {
                startFillingForm();
                genDataButton(response.data, statusFiltering);
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

