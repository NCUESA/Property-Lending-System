var selectedItems = new Set();  //借用清單彙整

$(document).ready(function () {
    $('input[name="borrow_place"]').on('change', function () {
        selectedItems.clear();  // 避免跨地點借用器材

        var selected_place = $(this).val();

        $.ajax({
            type: 'POST',
            url: '/show-borrowable-item',
            data: {
                place: selected_place,
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                //console.log(response);
                if (response.success) {
                    genPropertyTable(response.data);
                    refreshFilter(response.data);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });
    $('#find').on('change', function () {
        // Adding-Selected-Items       
        let arr = collectBorrowItems();
        
        arr.forEach(num => selectedItems.add(num));

        // Filetering-Search
        var condition = $(this).val();
        var selected_place = $('input[name="borrow_place"]:checked').val();
        //console.log(selected_place);
        $.ajax({
            type: 'POST',
            url: '/show-borrowable-item',
            data: {
                place: selected_place,
                filter: condition,
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                //console.log(response);
                if (response.success) {
                    genPropertyTable(response.data);
                }
            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    $('#send_form').on('click', function (event) {
        // The logic of precheck

        // Know Filling Empty
        if (!formValidationCheck()) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        // Transfer into Array
        let sending_items = [...selectedItems].flat();
        
        // Send Ajax
        $.ajax({
            type: 'POST',
            url: '/borrow/item/',
            data: {
                understand: $('input[name="know_filling"]').val(),
                borrow_place: $('input[name="borrow_place"]:checked').val(),
                borrow_department: $('#department').val(),
                borrow_person_name: $('#contact_person').val(),
                phone: $('#phone').val(),
                email: $('#email').val(),
                borrow_date: $('#borrow_date').val(),
                returned_date: $('#return_date').val(),
                borrow_items: sending_items,
                _token: $('meta[name="csrf-token"]').attr('content')  // CSRF Token
            },
            success: function (response) {
                //console.log(response);
                if (response.success && response.error == '') {
                    alert('借用表單送出成功，請等待值勤人員提供器材');
                    location.reload();
                }
                else {
                    alert(response.error);
                }
            },
            error: function (error) {
                console.log(error);
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

});
function refreshFilter(data) {
    $('#find').empty();
    $('#find').append(`<option value="">全部器材</option>`);
    let filter = new Set();
    $.each(data, function (index, item) {
        filter.add(item.name);
    });

    filter.forEach((type) => {
        let option = `<option value="${type}">${type}</option>`
        $('#find').append(option);
    });
}

function genPropertyTable(data) {
    $('#borrowable_item').empty(); // 清空容器
    $.each(data, function (index, item) {
        // 處理 null 值
        item.remark = item.remark || '';
        item.format = item.format || '';
        item.second_name = item.second_name || '';

        // 動態生成卡片結構
        let desktop_card = `
            <div class="col">
                <div class="card">
                    <input type="hidden" value="${item.ssid}">
                    <img src="./storage/propertyImgs/${item.img_url}" style="width: 100%; aspect-ratio: 4 / 3; object-fit: contain;" class="card-img-top" alt="No Image" onerror="this.src='https://dummyimage.com/1440x1080/cccccc/000000&text=No+Image';">
                    <div class="card-body">
                        <input type="checkbox" class="form-check-input" id="${item.ssid}" ${selectedItems.has(item.ssid) ? "checked" : ""}>
                        <label for="${item.ssid}">${item.name} ${item.second_name}</label>
                        <p>
                            <span class="badge bg-success" style="margin: 0.1rem">${item.belong_place || '未知'}</span>
                            <span class="badge bg-secondary" style="margin: 0.1rem">${item.ssid}</span>
                            <span class="badge bg-primary" style="margin: 0.1rem">${item.class}</span>
                        </p>
                        <p class="card-text">
                            規格：${item.format || '無'}<br>
                            ${item.remark ? `備註：${item.remark}` : ''}
                        </p>
                    </div>
                </div>
            </div>`;

        let mobile_card = `
                <div class="col">
                    <div class="card">
                        <input type="hidden" value="${item.ssid}">
                        <div class="card-body">
                            <div class="d-flex position-relative hstack gap-3">
                                <img src="./storage/propertyImgs/${item.img_url}" style="width: 40%; aspect-ratio: 1 / 1; object-fit: contain;" class="card-img-top" alt="No Image" onerror="this.src='https://dummyimage.com/1440x1080/cccccc/000000&text=No+Image';">
                                <div class="vr"></div>
                                <div>
                                    <input type="checkbox" class="form-check-input" id="${item.ssid}" ${selectedItems.has(item.ssid) ? "checked" : ""}>
                                    <label for="${item.ssid}">${item.name} ${item.second_name}</label>
                                    <p>
                                        <span class="badge bg-success" style="margin: 0.1rem">${item.belong_place || '未知'}</span>
                                        <span class="badge bg-secondary" style="margin: 0.1rem">${item.ssid}</span>
                                        <span class="badge bg-primary" style="margin: 0.1rem">${item.class}</span>
                                    </p>
                                    <p class="card-text">
                                        規格：${item.format || '無'}<br>
                                        ${item.remark ? `備註：${item.remark}` : ''}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;


        // 將生成的卡片添加到容器中
        if (window.innerWidth <= 768) {
            $('#borrowable_item').append(mobile_card);
        }
        else {
            $('#borrowable_item').append(desktop_card);
        }
    });
}


function collectBorrowItems() {
    var checkItems = [];
    $('#borrowable_item input[type="checkbox"]:checked').each(function () {
        // 獲取被勾選 checkbox 的 id
        checkItems.push($(this).attr('id'));

        //console.log(checkboxId); // 在這裡可以處理 id
    });

    return checkItems;
}

function formValidationCheck() {
    let isValid = true;
    if (!$('input[name="know_filling"]:checked').val()) {
        $('#check_know_filling').addClass('invalid-feedback');
        $('#check_know_filling').text('要填');
        isValid = false;
    }
    // Know Filling Value Wrong
    else if ($('input[name="know_filling"]:checked').val() == 'n') {
        $('#check_know_filling').addClass('invalid-feedback');
        $('#check_know_filling').text('還敢亂填啊');
        isValid = false;
    }
    else {
        $('#check_know_filling').text('');
        $('#check_know_filling').removeClass('invalid-feedback').addClass('valid-feedback');
    }

    if (!$('input[name="borrow_place"]:checked').val()) {
        $('#check_borrow_place').addClass('invalid-feedback');
        $('#check_borrow_place').text('要填');
        isValid = false;
    }
    else {
        $('#check_borrow_place').text('');
        $('#check_borrow_place').removeClass('invalid-feedback').addClass('valid-feedback');
    }

    if ($('#department').val().trim() === '') {
        $('#check_department').addClass('invalid-feedback');
        $('#check_department').text('要填');
        isValid = false;
    }
    else {
        $('#check_department').text('');
        $('#check_department').removeClass('invalid-feedback').addClass('valid-feedback');
    }


    if ($('#contact_person').val().trim() == '') {
        $('#check_contact_person').addClass('invalid-feedback');
        $('#check_contact_person').text('要填');
        isValid = false;
    }
    else {
        $('#check_contact_person').text('');
        $('#check_contact_person').removeClass('invalid-feedback').addClass('valid-feedback');
    }

    const phoneRegex = /^\d{10}$/;
    if (!phoneRegex.test($('#phone').val().trim())) {
        $('#check_phone').addClass('invalid-feedback');
        $('#check_phone').text('要填');
        isValid = false;
    }
    else {
        $('#check_phone').text('');
        $('#check_phone').removeClass('invalid-feedback').addClass('valid-feedback');
    }

    if ($('#email').val().trim() == '') {
        $('#check_email').addClass('invalid-feedback');
        isValid = false;
        $('#check_email').text('要填');
    } else if (!/\S+@\S+\.\S+/.test($('#email').val().trim())) {
        $('#check_email').addClass('invalid-feedback');
        isValid = false;
        $('#check_email').text('不要亂填');
    }
    else {
        $('#check_email').text('');
        $('#check_email').removeClass('invalid-feedback').addClass('valid-feedback');
    }

    // 獲取當前日期
    const today = new Date();
    today.setHours(0, 0, 0, 0); // 設定時間為午夜，確保只比較日期

    // 獲取欄位值
    const borrowDateValue = $('#borrow_date').val();
    const returnDateValue = $('#return_date').val();



    // 將日期字串轉為日期物件
    const borrowDate = borrowDateValue ? new Date(borrowDateValue) : null;
    const returnDate = returnDateValue ? new Date(returnDateValue) : null;

     // 設定時間為午夜，確保只比較日期
    borrowDate.setHours(0, 0, 0, 0);
    returnDate.setHours(0, 0, 0, 0);

    // 檢查 borrow_date
    if (!borrowDateValue) {
        $('#check_borrow_date').addClass('invalid-feedback').text('要填');
        isValid = false;
    } else if (borrowDate < today) {
        $('#check_borrow_date').addClass('invalid-feedback').text('你應該不是時間旅人吧');
        isValid = false;
    } else if (borrowDate > today) {
        $('#check_borrow_date').addClass('invalid-feedback').text('不開放預借，借用日期不能大於當前日期');
        isValid = false;
    } else {
        $('#check_borrow_date').removeClass('invalid-feedback').addClass('valid-feedback').text('');
    }

    // 檢查 return_date
    if (!returnDateValue) {
        $('#check_return_date').addClass('invalid-feedback').text('要填');
        isValid = false;
    } else if (returnDate < borrowDate) {
        $('#check_return_date').addClass('invalid-feedback').text('歸還日期不能小於借用日期');
        isValid = false;
    } else {
        $('#check_return_date').removeClass('invalid-feedback').addClass('valid-feedback').text('');
    }


    let arr = collectBorrowItems();
    arr.forEach(num => selectedItems.add(num));

    if (selectedItems.length <= 0) {
        //$('#check_borrow_item').addClass('invalid-feedback');
        $('#check_borrow_item').show();
        isValid = false;
    }
    else {
        $('#check_borrow_item').hide();
        //$('#check_borrow_item').removeClass('invalid-feedback').addClass('valid-feedback');
    }

    $("#borrow").addClass('was-validated');

    return isValid;
}
