var selectedItems = new Set();  //借用清單彙整

$(document).ready(function () {
    $('input[name="borrow_place"]').on('change', function () {
        selectedItems.clear();  // 避免跨地點借用器材

        var selected_place = $(this).val();

        $.ajax({
            type: 'POST',
            url: '/property/borrowable/show',
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
            url: '/property/borrowable/show',
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
        spinnerLoadingAction('show');
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
                    spinnerLoadingAction('hide');
                    location.reload();
                }
                else {
                    alert(response.error);
                    event.stopPropagation();
                }
            },
            error: function (error) {
                alert(error);
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

function setToday(inputId) {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById(inputId).value = today;
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
    let firstErrorElement = null; // 存第一個錯誤欄位

    function setError(element, message) {
        element.addClass('invalid-feedback').text(message);
        if (!firstErrorElement) firstErrorElement = element; // 記住第一個錯誤的元素
    }

    function clearError(element) {
        element.removeClass('invalid-feedback').addClass('valid-feedback').text('');
    }

    // Know Filling
    const knowFilling = $('input[name="know_filling"]:checked').val();
    if (!knowFilling) {
        setError($('#check_know_filling'), '要填');
        isValid = false;
    } else if (knowFilling === 'n') {
        setError($('#check_know_filling'), '還敢亂填啊');
        isValid = false;
    } else {
        clearError($('#check_know_filling'));
    }

    // Borrow Place
    const borrowPlace = $('input[name="borrow_place"]:checked').val();
    if (!borrowPlace) {
        setError($('#check_borrow_place'), '要填');
        isValid = false;
    } else {
        clearError($('#check_borrow_place'));
    }

    // Department
    if ($('#department').val().trim() === '') {
        setError($('#check_department'), '要填');
        isValid = false;
    } else {
        clearError($('#check_department'));
    }

    // Contact Person
    if ($('#contact_person').val().trim() === '') {
        setError($('#check_contact_person'), '要填');
        isValid = false;
    } else {
        clearError($('#check_contact_person'));
    }

    // Phone
    const phoneRegex = /^\d{10}$/;
    if (!phoneRegex.test($('#phone').val().trim())) {
        setError($('#check_phone'), '要填');
        isValid = false;
    } else {
        clearError($('#check_phone'));
    }

    // Email
    const email = $('#email').val().trim();
    if (email === '') {
        setError($('#check_email'), '要填');
        isValid = false;
    } else if (!/\S+@\S+\.\S+/.test(email)) {
        setError($('#check_email'), '不要亂填');
        isValid = false;
    } else {
        clearError($('#check_email'));
    }

    // 日期驗證
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const borrowDateValue = $('#borrow_date').val();
    const returnDateValue = $('#return_date').val();

    const borrowDate = borrowDateValue ? new Date(borrowDateValue) : null;
    const returnDate = returnDateValue ? new Date(returnDateValue) : null;

    if (borrowDate) borrowDate.setHours(0, 0, 0, 0);
    if (returnDate) returnDate.setHours(0, 0, 0, 0);

    if (!borrowDateValue) {
        setError($('#check_borrow_date'), '要填');
        isValid = false;
    } else if (borrowDate < today) {
        setError($('#check_borrow_date'), '你應該不是時間旅人吧');
        isValid = false;
    } else if (borrowDate > today) {
        setError($('#check_borrow_date'), '不開放預借，借用日期不能大於當前日期');
        isValid = false;
    } else {
        clearError($('#check_borrow_date'));
    }

    if (!returnDateValue) {
        setError($('#check_return_date'), '要填');
        isValid = false;
    } else if (returnDate < today) {
        setError($('#check_return_date'), '你應該不是時間旅人吧');
        isValid = false;
    } else if (returnDate < borrowDate) {
        setError($('#check_return_date'), '歸還日期不能小於借用日期');
        isValid = false;
    } else {
        clearError($('#check_return_date'));
    }

    // 借用項目
    let arr = collectBorrowItems();
    arr.forEach(num => selectedItems.add(num));

    if (selectedItems.size <= 0) {
        $('#check_borrow_item').show();
        isValid = false;
        if (!firstErrorElement) firstErrorElement = $('#check_borrow_item'); // 記住第一個錯誤元素
    } else {
        $('#check_borrow_item').hide();
    }

    // 滾動到第一個錯誤元素
    if (firstErrorElement) {
        $('html,body').animate({
            scrollTop: firstErrorElement.offset().top - 100 // 預留一點空間
        }, 10);
    }

    $("#borrow").addClass('was-validated');

    return isValid;
}

function spinnerLoadingAction(action) {
    if (action == 'show') {
        $('#send_form').text(' 請求處理中...');
        $('#waiting_spot').removeClass('visually-hidden');
        $('.bi-send').addClass('visually-hidden');
        $('#send_form').prop('disabled', true);
    }
    else {
        $('#waiting_spot').addClass('visually-hidden');
        $('.bi-send').removeClass('visually-hidden');
        $('#send_form').prop('disabled', false);
        $('#send_form').text(' 送出借用');
    }
}
