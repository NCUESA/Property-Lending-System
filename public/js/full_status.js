$(document).ready(function () {
    
    /*if (sessionStorage.getItem('place')) {
        $('#place').val(sessionStorage.getItem('place'));
    }*/

    // 地點查詢
    $('#place').on('change', function () {
        $.ajax({
            type: 'POST',
            url: '/borrow/getData/',
            data: {
                location: $(this).val(),
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                //console.log(response);
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

    // 將資料帶入Modal邏輯
    $(document).on('click', '.btn-bring-data', function () {
        let combine_data = lending_data_store[$(this).data('combine')];
        let borrowListId = combine_data['borrow_list_id'];
        getPropertyWithID(borrowListId, function (lending_property) {
            bringDataIntoModal(combine_data, lending_property);
        });
    });

    // Modal 開始借用
    $('#start-lending').on('click', function () {
        $(':input').prop('disabled', false);
        $('#borrow_id').prop('disabled', true);
    });

    $('#scan_list').on('input', function () {
        const inputVal = $(this).val();

        if (inputVal.length >= 8) {
            let itemArray = [];

            // 從 modal 中的 tbody 名稱為 'borrow_list' 的每個 tr 中提取第一個 td 的值
            $('#borrow_list tr').each(function () {
                const firstTdValue = $(this).find('td:first').text().trim();
                itemArray.push(firstTdValue);
            });

            console.log(itemArray);

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
                alert("未借用此物品");
            }
            $('#scan_list').val('');
        }
    });

    $('#save-data').on('click', function () {
        const borrowListId = $(this).data('borrowListId');

        // 找到按鈕所在的 Modal，然後向上或向下找到相關的子區塊
        const modal = $('#modal'); // 找到最近的 modal

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
                    if (response.success && response.error == '') {
                        sessionStorage.setItem('place', $('#place').val());
                        alert('完成');
                        location.reload();
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
    });
});

var lending_data_store = {};
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
    console.log(combine_data);
    console.log(lending_property);

    $('#borrow_list').empty();
    let formattedInfo = '';
    $.each(lending_property, function (index, item) {
        let col = '<tr>';
        if (item.lending_status == 1) {
            item.lending_status = '外借中';
        }
        else if (item.lending_status == 2) {
            item.lending_status = '待借出';
        }
        else {
            return true;
        }
        col +=
            `<td>${item.ssid}</td>
            <td>${item.name}</td>
            <td>${item.second_name}</td>
            <td>${item.class}</td>
            <td>${item.format}</td>
            <td>${item.remark}</td>
            <td>${item.lending_status}</td>
            <td><img src="${item.img_url}"></td>
        `
        col += '</tr>'
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
function genDataButton(data) {
    $('#lending_status').empty();
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


        lending_data_store[item.id + '_combine'] = combine_data;
        // Lending Property Info


        //let bg_color = item.borrow_place == '進德' ? 'table-info' : 'table-secondary';

        // Generate modal and button
        let trig_btn = `<button type="button" class="btn btn-info btn-bring-data" data-bs-toggle="modal" data-bs-target="#modal" data-combine="${item.id}_combine">借用資訊</button>`;

        // Generate table row
        let row = `
            <tr class="">
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

